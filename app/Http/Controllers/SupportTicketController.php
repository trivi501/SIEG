<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCommentedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status', 'priority']);

        $tickets = SupportTicket::with('user', 'assignedUser', 'comments.user')
            ->when(auth()->user()->hasRole(['Super Admin', 'Admin']), fn($q) => $q)
            ->when(!auth()->user()->hasRole(['Super Admin', 'Admin']), fn($q) => $q->where('user_id', auth()->id()))
            ->when($filters['search'] ?? null, fn($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['priority'] ?? null, fn($q, $v) => $q->where('priority', $v))
            ->orderBy('created_at', 'desc')
            ->paginate(50)->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name']);

        return Inertia::render('support-tickets/Index', compact('tickets', 'filters', 'users'));
    }

    public function create()
    {
        return Inertia::render('support-tickets/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'url' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'priority' => 'required|in:baja,media,alta,urgente',
        ]);

        $data = [
            ...$validated,
            'user_id' => auth()->id(),
            'status' => 'abierto',
        ];

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('tickets', 'public');
        }

        $ticket = SupportTicket::create($data);

        $admins = User::role(['Super Admin', 'Admin'])->get();
        foreach ($admins as $admin) {
            $admin->notify(new TicketCreatedNotification($ticket));
        }

        return redirect()->route('support-tickets.show', $ticket)
            ->with('success', 'Ticket creado exitosamente.');
    }

    public function show(SupportTicket $supportTicket)
    {
        $supportTicket->load('user', 'assignedUser', 'comments.user');
        $users = User::orderBy('name')->get(['id', 'name']);

        return Inertia::render('support-tickets/Show', [
            'ticket' => $supportTicket,
            'users' => $users,
            'canAssign' => auth()->user()->hasRole(['Super Admin', 'Admin']),
        ]);
    }

    public function update(Request $request, SupportTicket $supportTicket)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'url' => 'nullable|string|max:500',
            'priority' => 'sometimes|in:baja,media,alta,urgente',
            'status' => 'sometimes|in:abierto,en_proceso,resuelto,cerrado',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $oldStatus = $supportTicket->status;

        $supportTicket->update($validated);

        if (isset($validated['assigned_to']) && $validated['assigned_to'] != $supportTicket->assigned_to) {
            $assigned = User::find($validated['assigned_to']);
            if ($assigned) {
                $assigned->notify(new TicketAssignedNotification($supportTicket));
            }
        }

        if (isset($validated['status']) && in_array($validated['status'], ['resuelto', 'cerrado']) && $oldStatus !== $validated['status']) {
            $supportTicket->user->notify(new TicketResolvedNotification($supportTicket));
        }

        return redirect()->back()->with('success', 'Ticket actualizado.');
    }

    public function comment(Request $request, SupportTicket $supportTicket)
    {
        $validated = $request->validate([
            'comment' => 'required|string',
        ]);

        TicketComment::create([
            'ticket_id' => $supportTicket->id,
            'user_id' => auth()->id(),
            'comment' => $validated['comment'],
        ]);

        $notify = collect();
        $commenterName = auth()->user()->name;

        if ($supportTicket->user && $supportTicket->user_id !== auth()->id()) {
            $notify->push($supportTicket->user);
        }

        if ($supportTicket->assignedUser && $supportTicket->assigned_to !== auth()->id()) {
            $notify->push($supportTicket->assignedUser);
        }

        $notify = $notify->unique('id');

        if ($notify->isNotEmpty()) {
            $notification = new TicketCommentedNotification(
                $supportTicket,
                $validated['comment'],
                $commenterName
            );

            foreach ($notify as $user) {
                $user->notify($notification);
            }
        }

        $supportTicket->load('comments.user');

        return redirect()->back()->with('success', 'Comentario agregado.');
    }

    public function notifications()
    {
        return response()->json([
            'unread' => auth()->user()->unreadNotifications->map(fn($n) => [
                'id' => $n->id,
                'message' => $n->data['message'] ?? '',
                'ticket_id' => $n->data['ticket_id'] ?? null,
                'created_at' => $n->created_at->diffForHumans(),
            ]),
            'count' => auth()->user()->unreadNotifications->count(),
        ]);
    }

    public function markNotification(DatabaseNotification $notification)
    {
        $notification->markAsRead();
        return response()->json(['success' => true]);
    }
}

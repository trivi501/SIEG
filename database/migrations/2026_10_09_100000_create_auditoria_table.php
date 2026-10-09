<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitácora de auditoría: quién dio de alta, modificó o dio de baja un registro de catálogo,
     * con los valores anteriores y nuevos. Una importación masiva comparte el mismo `lote`.
     */
    public function up(): void
    {
        if (Schema::hasTable('auditoria')) {
            return;
        }

        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 20);
            $table->string('auditable_type', 150);
            $table->string('auditable_id', 50);
            $table->string('descripcion', 300)->nullable();
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->uuid('lote')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};

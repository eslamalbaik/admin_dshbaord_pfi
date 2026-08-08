<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سجل محادثة كامل لكل تذكرة دعم (بدل حقل reply الواحد) — يسمح بتبادل رسائل
 * متعدد بين المقاول والإدارة، ويحافظ على تاريخ كيف انحلّت المشكلة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->enum('sender_type', ['contractor', 'admin']);
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->text('message');
            $table->string('attachment')->nullable();
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at']);
        });

        // ترحيل الرد الوحيد الموجود مسبقاً (reply) إلى أول رسالة بالمحادثة
        DB::table('support_tickets')
            ->whereNotNull('reply')
            ->orderBy('id')
            ->get()
            ->each(function ($ticket) {
                DB::table('support_ticket_messages')->insert([
                    'support_ticket_id' => $ticket->id,
                    'sender_type'       => 'admin',
                    'sender_id'         => $ticket->replied_by,
                    'message'           => $ticket->reply,
                    'created_at'        => $ticket->replied_at ?? $ticket->updated_at,
                    'updated_at'        => $ticket->replied_at ?? $ticket->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
    }
};

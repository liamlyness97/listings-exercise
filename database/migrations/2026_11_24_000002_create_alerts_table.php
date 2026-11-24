<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();

            // When the user was told about this alert outside the app. Nothing
            // sets it yet: it is the hook a digest job would use to pick up
            // unsent alerts and stamp them once sent.
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            // One alert per user per listing, however many of their searches it
            // matches. Enforced here so a retried or concurrent job can't create
            // a duplicate, and a relisted property doesn't alert twice.
            $table->unique(['user_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};

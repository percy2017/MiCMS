<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->json('parameters')->nullable();
            $table->string('description')->nullable();
            $table->string('expression')->default('* * * * *');
            $table->string('timezone')->nullable();
            $table->boolean('without_overlapping')->default(false);
            $table->boolean('on_one_server')->default(false);
            $table->boolean('run_in_maintenance')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_tasks');
    }
};

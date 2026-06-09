<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_widgets', function (Blueprint $table) {
            $table->dropColumn('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_widgets', function (Blueprint $table) {
            $table->string('primary_color', 16)->default('#2563eb');
        });
    }
};

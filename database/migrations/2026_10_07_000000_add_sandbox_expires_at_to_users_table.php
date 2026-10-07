<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set only for throwaway "Try the demo" accounts; pruned once it passes.
            $table->timestamp('sandbox_expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sandbox_expires_at');
        });
    }
};

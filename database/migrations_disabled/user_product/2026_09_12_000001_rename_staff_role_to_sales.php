<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'staff')->update(['role' => 'sales']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('sales')->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'sales')->update(['role' => 'staff']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff')->change();
        });
    }
};

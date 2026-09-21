<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff')->after('email');
        });

        DB::table('users')
            ->where(function ($query) {
                $query->where('username', 'admin')
                    ->orWhere('email', 'admin@admin.com');
            })
            ->update(['role' => 'admin']);

        if (DB::table('users')->where('role', 'admin')->doesntExist()) {
            DB::table('users')->orderBy('id')->limit(1)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};

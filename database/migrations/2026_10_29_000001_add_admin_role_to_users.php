<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // null = super operator (kompatibel dengan operator yang sudah ada); lainnya dibatasi menurut config('admin.roles')
        Schema::table('users', function (Blueprint $t) {
            $t->string('admin_role', 20)->nullable()->after('is_platform_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('admin_role'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('addresses', 'biteship_area_id')) {
                $table->string('biteship_area_id')
                    ->nullable()
                    ->after('district_id');
            }

            if (! Schema::hasColumn('addresses', 'biteship_area_label')) {
                $table->string('biteship_area_label')
                    ->nullable()
                    ->after('biteship_area_id');
            }

            if (! Schema::hasColumn('addresses', 'biteship_postal_code')) {
                $table->string('biteship_postal_code')
                    ->nullable()
                    ->after('biteship_area_label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $columns = [
                'biteship_area_id',
                'biteship_area_label',
                'biteship_postal_code',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('addresses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

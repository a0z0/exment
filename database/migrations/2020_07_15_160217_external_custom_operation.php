<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ExternalCustomOperation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(Schema::hasTable('custom_operations')){
            Schema::table('custom_operations', function (Blueprint $table) {
                if (!Schema::hasColumn('custom_operations', 'operation_type')) {
                    $table->string('operation_type')->after('custom_table_id')->nullable();
                }
            });
        }
        if(Schema::hasTable('custom_operation_columns')){
            Schema::table('custom_operation_columns', function (Blueprint $table) {
                if (!Schema::hasColumn('custom_operation_columns', 'options')) {
                    $table->json('options')->nullable();
                }
            });
        }
        \Artisan::call('exment:patchdata', ['action' => 'init_custom_operation_type']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if(Schema::hasTable('custom_operations')){
            Schema::table('custom_operations', function (Blueprint $table) {
                if(Schema::hasColumn('custom_operations', 'operation_type')){
                    $table->dropColumn('operation_type');
                }
            });
        }
        if(Schema::hasTable('custom_operation_columns')){
            Schema::table('custom_operation_columns', function (Blueprint $table) {
                if(Schema::hasColumn('custom_operation_columns', 'options')){
                    $table->dropColumn('options');
                }
            });
        }
    }
}

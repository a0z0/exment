<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Enums\SystemTableName;

class CreateAuth2factor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if(!\Schema::hasTable(SystemTableName::LOGIN_2FACTOR_VERIFY)){
            Schema::create(SystemTableName::LOGIN_2FACTOR_VERIFY, function (Blueprint $table) {
                $table->increments('id');
                $table->integer('login_user_id')->unsigned();
                $table->string('verify_type');
                $table->string('email');
                $table->string('verify_code');
                $table->datetime('valid_period_datetime');
                $table->timestamps();
            });
        }

        if(\Schema::hasTable('login_users') && !\Schema::hasColumn('login_users', 'auth2fa_key')){
            Schema::table('login_users', function (Blueprint $table) {
                $table->text('auth2fa_key')->nullable()->after('avatar');
                $table->boolean('auth2fa_available')->default(false)->after('auth2fa_key');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists(SystemTableName::LOGIN_2FACTOR_VERIFY);
    }
}

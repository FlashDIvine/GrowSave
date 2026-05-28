<?php
// database/migrations/xxxx_xx_xx_add_crowdfunding_fields_to_bills_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->bigInteger('target_amount')->default(0)->after('description');
            $table->bigInteger('required_amount')->default(0)->after('target_amount');
            $table->bigInteger('collected_amount')->default(0)->after('required_amount');
            $table->boolean('is_completed')->default(false)->after('collected_amount');
            $table->timestamp('completed_at')->nullable()->after('is_completed');
        });
    }

    public function down()
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['target_amount', 'required_amount', 'collected_amount', 'is_completed', 'completed_at']);
        });
    }
};

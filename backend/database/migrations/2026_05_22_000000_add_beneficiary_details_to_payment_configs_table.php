<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->string('bank_name')->nullable();
            $table->string('bank_id')->nullable();
            $table->string('account_no')->nullable();
            $table->string('account_name')->nullable();
            $table->text('qr_template')->nullable();
        });

        // Seed default beneficiary details for existing configs
        \DB::table('payment_configs')->update([
            'bank_name' => 'MB Bank',
            'bank_id' => '970416',
            'account_no' => '11183041',
            'account_name' => 'DANG TUAN DAT',
            'qr_template' => 'https://api.vietqr.io/image/{bank_id}-{account_no}-rdXzPHV.jpg?accountName={account_name}&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_id', 'account_no', 'account_name', 'qr_template']);
        });
    }
};

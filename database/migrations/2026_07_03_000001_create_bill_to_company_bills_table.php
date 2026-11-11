<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Force this migration to always run on the main app DB,
    // not on the expense/company-specific connection that may be active in session.
    protected $connection = 'mysql';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('mysql')->create('bill_to_company_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('company_id')->default(1)->comment('1=VNR Seeds, 3=VNR Nursery, 11=VVNR');
            $table->unsignedBigInteger('employee_id')->comment('EmployeeID from HRIMS');
            $table->string('emp_code', 20)->nullable()->comment('Employee code');
            $table->string('emp_name', 150)->nullable()->comment('Employee full name');
            $table->string('bill_title', 255)->comment('Title/description of the bill');
            $table->string('bill_category', 100)->nullable()->comment('Category: Travel, Medical, Hotel, Misc, etc.');
            $table->decimal('bill_amount', 12, 2)->nullable()->comment('Bill amount');
            $table->date('bill_date')->nullable()->comment('Date on the bill');
            $table->string('file_path', 500)->comment('Storage path of the uploaded bill file');
            $table->string('file_name', 255)->comment('Original file name');
            $table->string('file_type', 50)->nullable()->comment('mime type / extension');
            $table->text('remarks')->nullable()->comment('Optional remarks from admin');
            $table->tinyInteger('status')->default(1)->comment('1=Active, 0=Inactive/Deleted');
            $table->unsignedBigInteger('uploaded_by')->nullable()->comment('User ID who uploaded the bill');
            $table->timestamps();

            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'bill_date']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('bill_to_company_bills');
    }
};

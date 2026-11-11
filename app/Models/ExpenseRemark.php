<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseRemark extends Model
{

    protected $primaryKey = 'rid';
    public $timestamps = false;
    protected $table;
    protected $fillable = ['ExpId', 'Remark', 'By', 'ByRole', 'CrDateTime'];

    public static function tableName(int $yearId): string
    {
        return 'y' . $yearId . '_expenseremark';
    }
}

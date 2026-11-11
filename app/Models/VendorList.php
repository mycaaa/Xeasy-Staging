<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorList extends Model
{
    use HasFactory;

    protected $table = 'vendor_list';
    protected $fillable = ['vendor_type_id', 'vendor_type', 'vendor_name', 'vendor_address', 'contact_person', 'contact_number', 'email', 'gst_no', 'company_id', 'created_by', 'created_date', 'updated_by', 'updated_at', 'status', 'request_status',];
    protected $casts = ['created_date' => 'datetime', 'updated_at' => 'datetime', 'status' => 'string', 'request_status' => 'string',];
    public $timestamps = false;
    public function vendorType()
    {
        return $this->belongsTo(VendorType::class, 'vendor_type_id');
    }
    public static function getActiveVendorsByType($type = null, $byId = true)
    {
        $query = self::where('status', '1');

        if (!empty($type)) {
            if ($byId) {
                $query->where('vendor_type_id', $type);
            } else {
                $query->where('vendor_type', $type);
            }
        }

        return $query->get();
    }
}

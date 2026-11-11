<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\CommonHelper;

class G1ExpenseFillData extends Model
{
    protected $primaryKey = "did";
    public $timestamps = false;

    protected $fillable = [
        "ExpId", "DocName", "CityCategory", "CityName",
        "HotelName", "HotelAddress", "HotelContact", "HotelEmail",
        "BillingName", "BillingAddress", "BillNo", "BillDate",
        "Arrival", "Departure", "StayDuration", "RoomRateType",
        "Plan", "NoOfPAX", "PaxDetails", "PaxDetails_Other",
        "GST", "BillingInstruction", "PaymentCondition", "Mode",
        "Agent", "PNRNo", "TrainBusName", "Quota", "Class",
        "BookingDate", "JourneyStartDt", "JourneyEndDt",
        "JourneyFrom", "Journey_From_Id", "JourneyUpto", "Journey_Upto_Id",
        "PassengerDetail", "BookingStatus", "TravelInsurance", "IsTravelInsurance",
        "AgencyName", "AgencyAddress", "Invoice", "VehicleType",
        "VehicleReg", "DailyBasisCharges", "FuelRate", "KmsTrv",
        "CalAmount", "FixedAmount", "KmBasisCharges", "DistTraOpen",
        "DistTraClose", "DriverCharges", "OtherCharges",
        "MobileService", "Mobile", "BillingCycle", "PrevBalance",
        "LastPayement", "DueDate", "PaymentMode", "RechargeAmt",
        "PaymenDetails", "ServiceProvider", "SenderName", "SenderAddress",
        "ReceiverName", "ReceiverAddress", "DocketNumber",
        "DocketBookedDt", "WeightCharged", "DAAmount", "Vendor_Id",
    ];

    /*
    |--------------------------------------------------------------------------
    | Dynamic Table
    |--------------------------------------------------------------------------
    */
    public static function tableName($yearId): string
    {
        $yearIdInt = (int) $yearId;
        return "y{$yearIdInt}_g1_expensefilldata";
    }

    public static function setYearTable($yearId): self
    {
        $instance = new static();
        $instance->setTable(self::tableName($yearId));
        return $instance;
    }

    /*
    |--------------------------------------------------------------------------
    | Auto NULL Handling (VERY IMPORTANT)
    |--------------------------------------------------------------------------
    */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->DocName = $model->DocName ?? null;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Date Mutators (AUTO FORMAT)
    |--------------------------------------------------------------------------
    */
    public function setBillDateAttribute($value)
    {
        $this->attributes['BillDate'] = CommonHelper::toDatabaseFormat($value);
    }

    public function setBookingDateAttribute($value)
    {
        $this->attributes['BookingDate'] = CommonHelper::toDatabaseFormat($value);
    }

    public function setJourneyStartDtAttribute($value)
    {
        $this->attributes['JourneyStartDt'] = CommonHelper::toDatabaseFormat($value);
    }

    public function setJourneyEndDtAttribute($value)
    {
        $this->attributes['JourneyEndDt'] = CommonHelper::toDatabaseFormat($value);
    }

    public function setDueDateAttribute($value)
    {
        $this->attributes['DueDate'] = CommonHelper::toDatabaseFormat($value);
    }
}
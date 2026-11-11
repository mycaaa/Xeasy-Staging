<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityCategory;
use App\Models\ActivityName;
use App\Models\ActivityType;
use App\Models\ExpenseHeadMapping;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index()
    {
        $type = request('type', 'category'); 
        $status = request('status', 'active');

        
        $map = [
            'category' => [
                'model' => ActivityCategory::class,
                'view'  => 'admin.activity_category',
                'with'  => []
            ],
            'type' => [
                'model' => ActivityType::class,
                'view'  => 'admin.activity_type',
                'with'  => ['category']
            ],
            'name' => [
                'model' => ActivityName::class,
                'view'  => 'admin.activity_name',
                'with'  => ['category', 'claimType']
            ],
        ];

        if (!isset($map[$type])) {
            abort(404); 
        }

        $modelClass = $map[$type]['model'];
        $query = $modelClass::query();

        
        if (!empty($map[$type]['with'])) {
            $query->with($map[$type]['with']);
        }

        
        if ($status === 'active') {
            $query->where('status', 1);
        } elseif ($status === 'inactive') {
            $query->where('status', 0);
        } 

        $data = $query->get();

        
        $variableName = match ($type) {
            'category' => 'activityCategories',
            'type'     => 'activityTypes',
            'name'     => 'activityNames',
        };

        return view($map[$type]['view'], [
            $variableName => $data,
            'status'      => $status
        ]);
    }
}

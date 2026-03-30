<?php

namespace App\Models\Contribution;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollFilemaker extends Model
{
    use HasFactory;

    public static function data_period($date_import)
    {
        $data = collect([]);
        $exists_data = true;
        $payroll = PayrollFilemaker::whereDate('created_at', '=', $date_import)->count('id');
        if($payroll == 0) $exists_data = false;

        $data['exist_data'] = $exists_data;
        $data['count_data'] = $payroll;

        return  $data;
    }
}

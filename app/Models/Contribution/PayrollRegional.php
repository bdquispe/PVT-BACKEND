<?php

namespace App\Models\Contribution;

use App\Models\Affiliate\Affiliate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRegional extends Model
{
    use HasFactory;

    public static function data_period($date_import)
    {
        $data = collect([]);
        $exists_data = true;
        $payroll = PayrollRegional::whereDate('created_at', '=', $date_import)->count('id');
        if($payroll == 0) $exists_data = false;

        $data['exist_data'] = $exists_data;
        $data['count_data'] = $payroll;
        
        return  $data;
    }
    
    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }
}

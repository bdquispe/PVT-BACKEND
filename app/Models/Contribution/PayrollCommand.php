<?php

namespace App\Models\Contribution;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Affiliate\Affiliate;
use App\Models\Contribution\Contribution;
use App\Models\Affiliate\Unit;
use App\Models\Affiliate\Degree;
use App\Models\Affiliate\Breakdown;
use App\Models\Affiliate\Category;
use App\Models\Affiliate\Hierarchy;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollCommand extends Model
{
    protected $table = "payroll_commands";
    use HasFactory;
    use SoftDeletes;
    public $timestamps = true;
    public $guarded = ['id'];
    protected $fillable = [
        'affiliate_id',
        'unit_id',
        'breakdown_id',
        'month_p',
        'year_p',
        'identity_card',
        'last_name',
        'mothers_last_name',
        'surname_husband',
        'first_name',
        'second_name',
        'civil_status',
        'nivel',
        'grade',
        'gender',
        'base_wage',
        'seniority_bonus',
        'study_bonus',
        'position_bonus',
        'border_bonus',
        'east_bonus',
        'gain',
        'total',
        'payable_liquid',
        'birth_date',
        'date_entry',
        'affiliate_type',
        'reimbursement'
    ];
    
    public function payroll_command_contribution()
    {
        return $this->morphOne(Contribution::class,'contributionable');
    }
    
    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
    public function degree()
    {
        return $this->belongsTo(Degree::class);
    }
    public function breakdown()
    {
        return $this->belongsTo(Breakdown::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function hierarchy()
    {
        return $this->belongsTo(Hierarchy::class);
    }
    public static function data_period($month,$year,$reimbursement)
    {
        $data = collect([]);
        $exists_data = true;
        $payroll = PayrollCommand::whereMonth_p($month)->whereYear_p($year)->whereReimbursement($reimbursement)->count('id');
        if($payroll == 0) $exists_data = false;

        $data['exist_data'] = $exists_data;
        $data['count_data'] = $payroll;

        return  $data;
    }
    public static function data_count($month,$year,$reimbursement)
    {
        $data = collect([]);
        $data['validated'] = PayrollCommand::whereMonth_p($month)->whereYear_p($year)->whereReimbursement($reimbursement)->count('id');
        $data['regular'] = PayrollCommand::whereMonth_p($month)->whereYear_p($year)->whereReimbursement($reimbursement)->whereAffiliate_type('REGULAR')->count('id');
        $data['new'] = PayrollCommand::whereMonth_p($month)->whereYear_p($year)->whereReimbursement($reimbursement)->whereAffiliate_type('NUEVO')->count('id');

        return  $data;
    }
}

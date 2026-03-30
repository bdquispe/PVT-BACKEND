<?php

namespace App\Models\Contribution;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Admin\User;
use App\Models\Affiliate\Affiliate;
use App\Models\Affiliate\Breakdown;
use App\Models\Affiliate\Category;
use App\Models\Affiliate\Degree;
use App\Models\Affiliate\Unit;
use App\Models\Contribution\ContributionRate;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contribution extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    protected $dates = ['deleted_at'];
    public $timestamps = true;
    public $guarded = ['id'];
    protected $fillable = [
        'user_id',
        'affiliate_id',
        'degree_id',
        'unit_id',
        'breakdown_id',
        'category_id',
        'month_year',
        'type',
        'base_wage',
        'seniority_bonus',
        'study_bonus',
        'position_bonus',
        'border_bonus',
        'east_bonus',
        'public_security_bonus',
        'gain',
        'payable_liquid',
        'quotable',
        'retirement_fund',
        'mortuary_quota',
        'subtotal',
        'interest',
        'total',
        'created_at',
        'updated-at',
        'deleted_at',
        'contribution_type_id'
    ];

    public function contribution_type()
    {
        return $this->belongsTo(ContributionType::class);
    }
    public function affiliate() 
    {
        return $this->belongsTo(Affiliate::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function degree()
    {
        return $this->belongsTo(Degree::class);
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function contributionable()
    {
        return $this->morphTo();
    }
    public function breakdown()
    {
        return $this->belongsTo(Breakdown::class);
    }

    public static function data_period_command($month_year)
    {
        $data = collect([]);
        $exists_data = true;
        $contribution =  Contribution::whereMonth_year($month_year)->whereContributionable_type('payroll_commands')->count('id');
        if($contribution == 0) $exists_data = false;

        $data['exist_data'] = $exists_data;
        $data['count_data'] = $contribution;

        return  $data;
    }

    public static function sum_total_command($month_year)
    {
        $contribution =  Contribution::whereMonth_year($month_year)->whereContributionable_type('payroll_commands')->sum('total');
        return $contribution;
    }
    public static function exist_contribution_rate($month_year)
    {
        return ContributionRate::whereMonth_year($month_year)->get()->first()? true:false;
    }
    public function can_deleted(){
        return is_null($this->contribution_type_id) && is_null($this->contribution_type_mortuary_id) ? true : false;        
    }

}

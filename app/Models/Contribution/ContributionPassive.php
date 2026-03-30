<?php

namespace App\Models\Contribution;

use App\Models\Affiliate\Affiliate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
class ContributionPassive extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    public $timestamps = true;
    public $guarded = ['id'];
    protected $fillable = [
        'user_id',
        'affiliate_id',
        'month_year',
        'quotable',
        'rent_pension',
        'dignity_rent',
        'interest',
        'total',
        'affiliate_rent_class',
        'contribution_state_id',
        'contributionable_type',
        'contributionable_id',
        'aps_total_cc',
        'aps_total_fsa',
        'aps_total_fs',
        'aps_total_death',
        'aps_disability',
        'aps_reimbursement'
    ];
    
    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }
    public function user()
    {
        return $this->hasOne(User::class,'id','user_id');
    }
    public function contributionable()
    {
        return $this->morphTo();
    }
    public function contribution_state()
    {
        return $this->belongsTo(ContributionState::class);
    }

    public static function data_period_senasir($month_year)
    {
        $data = collect([]);
        $exists_data = true;
        $contribution =  ContributionPassive::whereMonth_year($month_year)->whereContributionable_type('payroll_senasirs')->count('id');
        if($contribution == 0) $exists_data = false;

        $data['exist_data'] = $exists_data;
        $data['count_data'] = $contribution;

        return  $data;
    }

    public static function sum_total_senasir($month_year)
    {
        $contribution =  ContributionPassive::whereMonth_year($month_year)->whereContributionable_type('payroll_senasirs')->sum('total');
        return $contribution;
    }

    public function can_deleted(){
        return $this->total < 1 || is_null($this->contributionable_type) || ($this->contribution_state_id == 1 && $this->contributionable_type == 'discount_type_economic_complement')? true:false;
        return is_null($this->contribution_type_mortuary_id) || $this->contributionable_type == 'discount_type_economic_complement' ? true: false;
    }
}

<?php

namespace App\Console\Commands\Traits;

use Illuminate\Support\Collection;


trait GetAdditionalAssessmentMetrics
{
    

    public function getAdditionalMetrics() : Collection 
    {
        

        return collect();
    }
}

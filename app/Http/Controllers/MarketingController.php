<?php

namespace App\Http\Controllers;

use App\Services\PlanCatalog;

class MarketingController extends Controller
{
    public function landing()
    {
        return view('marketing.landing', ['plans' => PlanCatalog::purchasable()]);
    }
}

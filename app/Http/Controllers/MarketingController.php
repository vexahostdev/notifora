<?php

namespace App\Http\Controllers;

class MarketingController extends Controller
{
    public function landing()
    {
        return view('marketing.landing', ['plans' => config('notifora.plans')]);
    }
}

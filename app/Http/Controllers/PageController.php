<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('pages.home');
    }

    /**
     * Standalone PWA splash screen: logo animation, then into the app.
     */
    public function splash(): View
    {
        return view('play.splash');
    }

    public function about(): View
    {
        return view('pages.about');
    }

    public function privacy(): View
    {
        return view('pages.privacy');
    }

    public function refund(): View
    {
        return view('pages.refund');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }
}

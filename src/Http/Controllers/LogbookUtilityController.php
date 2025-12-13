<?php

namespace EmranAlhaddad\StatamicLogbook\Http\Controllers;

use Illuminate\Http\Request;

class LogbookUtilityController
{
    public function __invoke(Request $request)
    {
        // default page: system
        return redirect()->to(cp_route('utilities.logbook.system'));
    }

    public function system(Request $request)
    {
        // Stage 5B: هنا بنجيب records + filters + pagination
        return view('statamic-logbook::cp.logbook.system', [
            'filters' => $request->all(),
        ]);
    }

    public function audit(Request $request)
    {
        // Stage 5C: هنا بنجيب records + filters + pagination
        return view('statamic-logbook::cp.logbook.audit', [
            'filters' => $request->all(),
        ]);
    }
}

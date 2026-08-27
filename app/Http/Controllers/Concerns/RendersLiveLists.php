<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A list that can answer with its rows alone.
 *
 * The same action serves both: a browser gets the whole page, and a live
 * filter asks for the part it is about to replace. One set of rules decides
 * what matches either way - there is no second implementation of searching or
 * paging anywhere.
 *
 * The header is what asks. Nothing else about the request changes, so the URL
 * a live filter fetches is exactly the URL that would have been navigated to,
 * which is why it can be put in the address bar afterwards.
 */
trait RendersLiveLists
{
    /** @param  array<string, mixed>  $data */
    protected function liveList(Request $request, string $page, string $rows, array $data): View
    {
        return view($request->hasHeader('X-Live-List') ? $rows : $page, $data);
    }
}

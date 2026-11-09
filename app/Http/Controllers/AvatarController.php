<?php

namespace App\Http\Controllers;

use App\Support\Avatar;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /avatar?name=…&background=…&color=…&size=…&bold=true
 *
 * The initials picture for an account or department without an uploaded
 * picture (see App\Support\Avatar). Same details as the outside service it
 * replaces, drawn here so names stay inside EquipTrack.
 */
class AvatarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $svg = Avatar::svg(
            mb_substr((string) $request->query('name', ''), 0, 100),
            $request->query('background'),
            $request->query('color'),
            (int) $request->query('size', 64),
            filter_var($request->query('bold', false), FILTER_VALIDATE_BOOLEAN),
        );

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            // The picture for a name never changes: browsers keep it for a week.
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\RemoteStyleUpdate;

class RemoteStyleController extends Controller
{
        public function emisor()
    {
        $options = []; // Definimos la variable vacía para que no de error
        return view('emisor', compact('options'));
    }


    public function receptor()
    {
        return view('receptor');
    }

    public function emitir(Request $request)
    {
        $data = $request->validate([
            'newText' => ['required', 'string'],
            'newStyle' => ['nullable', 'string'],
        ]);

        // FORZADO CON TUS DATOS DEL .ENV ORIGINALES
        config(['broadcasting.connections.reverb.app_id' => '622505']);
        config(['broadcasting.connections.reverb.key' => 'uoj2o6k8snqvfgox3ewd']);
        config(['broadcasting.connections.reverb.secret' => 'sa98tgrijzlm7janzkhr']);

        broadcast(new RemoteStyleUpdate(
            $data['newText'],
            $data['newStyle'] ?? ''
        ));

        return back()->with('ok', 'Mensaje emitido correctamente.');
    }
}

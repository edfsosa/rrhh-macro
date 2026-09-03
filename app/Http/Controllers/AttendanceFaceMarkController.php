<?php

namespace App\Http\Controllers;

use App\Models\Terminal;
use Illuminate\Contracts\View\View as ViewContract;

class AttendanceFaceMarkController extends Controller
{
    /** Muestra la página de marcación facial */
    public function show(): ViewContract
    {
        return view('attendances.mark', ['title' => 'Marcación Facial']);
    }

    /** Muestra la página de marcación en modo terminal/kiosco (legacy — URL sin código) */
    public function terminal(): ViewContract
    {
        return view('attendances.terminal', ['title' => 'Terminal de Marcación']);
    }

    /**
     * Muestra la terminal de marcación identificada por su código único.
     * Actualiza last_seen_at en cada carga. Si está inactiva, muestra pantalla de fuera de servicio.
     *
     * @param  string  $code  Código único de 8 caracteres de la terminal
     */
    public function terminalByCode(string $code): ViewContract
    {
        $terminal = Terminal::with('branch.company')->where('code', $code)->first();

        if (! $terminal) {
            abort(404);
        }

        if ($terminal->isInactive()) {
            return view('attendances.terminal-inactive', [
                'title' => 'Terminal fuera de servicio',
                'terminal' => $terminal,
            ]);
        }

        $terminal->update(['last_seen_at' => now()]);

        // Sucursal — empresa en el título de pestaña: ayuda a diferenciar terminales de
        // distintas sucursales cuando un admin monitorea varios a la vez. Sin
        // sucursal/empresa cargada (dato incompleto), cae al nombre del propio terminal.
        $title = collect([$terminal->branch?->name, $terminal->branch?->company?->name])
            ->filter()
            ->implode(' — ');

        return view('attendances.terminal', [
            'title' => $title !== '' ? $title : "Terminal — {$terminal->name}",
            'terminal' => $terminal,
        ]);
    }
}

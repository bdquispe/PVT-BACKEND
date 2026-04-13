<?php

namespace App\Http\Controllers\Contribution;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\Util;
use Carbon\Carbon;

class ImportationController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/contribution/list_years/{type}",
     *      tags={"METODOS-GLOBALES-IMPORTACION"},
     *      summary="OBTIENE EL LISTADO DE AÑOS CONSECUTIVAMENTE ",
     *      operationId="list_years",
     *      description="Obtiene el listado de años de contribuciones de senasir de manera consecutiva hasta el año actual Ej 2023",
     *      @OA\Parameter(
     *         name="type",
     *         in="path",
     *         description="typo de importación, los valores son senasir, command, transcript ",
     *         example="transcript",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *       ),
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *            type="object"
     *         )
     *      )
     * )
     *
     * Logs user into the system.
     *
     * @param Request $request
     * @return void
    */
    public function list_years($type)
    {
        if (!is_string($type)) {
            return response()->json([
                'message' => "Error, el valor tipo no es cadena.",
                'payload' => [
                    'successfully' => false
                ],
            ]);
        }
        switch($type) {
            case 'senasir':
                $start_year = 1999;
                $end_year = Carbon::now()->format('Y');
                break;
            case 'command':
                $start_year = 2022;
                $end_year = Carbon::now()->format('Y');
                break;
            case 'transcript':
                $start_year = 1976;
                $end_year = 1999;
                break;
            case 'availability':
                $start_year = 2023;
                $end_year = Carbon::now()->format('Y');
                break;
            case 'regional':
                $start_year = 2026;
                $end_year = Carbon::now()->format('Y');
                break;
            default:
                $start_year = 1976;
                $end_year = Carbon::now()->format('Y');
                break;
        }
        return response()->json([
            'message' => "Éxito",
            'payload' => [
                'successfully' => true,
                'list_years' =>  Util::list_years($start_year,$end_year)
            ],
        ]);
     }
}

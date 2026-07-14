<?php

namespace Database\Seeders;

use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\Period;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BankProfileSeeder::class,
        ]);

        // The accountant. In production the Firebase UID is filled on first login;
        // here we seed a matching local record by email.
        User::updateOrCreate(
            ['email' => 'contador@ejemplo.com'],
            ['name' => 'Contador', 'firebase_uid' => '1WnhuqBHybVwiPQzqi8FxPXf1zH2'],
        );

        // A couple of sample clients so the dashboard isn't empty in dev.
        // $samples = [
        //     ['rfc' => 'XAXX010101000', 'razon_social' => 'Comercializadora del Golfo SA de CV', 'nombre_comercial' => 'Golfo Distribuciones', 'regimen_fiscal' => 'moral', 'codigo_postal' => '91700'],
        //     ['rfc' => 'MELM850101HDF', 'razon_social' => 'María Elena López Martínez', 'nombre_comercial' => null, 'regimen_fiscal' => 'fisica', 'codigo_postal' => '01000'],
        //     ['rfc' => 'TSA920315AB1', 'razon_social' => 'Tecnología y Servicios Avanzados SA', 'nombre_comercial' => 'TecServ', 'regimen_fiscal' => 'moral', 'codigo_postal' => '64000'],
        // ];

        // foreach ($samples as $data) {
        //     $client = Client::updateOrCreate(['rfc' => $data['rfc']], $data);
        //     $this->seedBasicCatalog($client);
        // }

        // Give the first client a current period at a mid-pipeline status for demo.
        $first = Client::first();
        if ($first) {
            Period::updateOrCreate(
                ['client_id' => $first->id, 'year' => now()->year, 'month' => now()->month],
                ['status' => PeriodStatus::Extracted, 'invoice_count' => 42, 'movement_count' => 38, 'matched_count' => 30],
            );
        }
    }

    /** A minimal SAT-coded catalog so account assignment has something to point at. */
    private function seedBasicCatalog(Client $client): void
    {
        $accounts = [
            ['codigo_agrupador' => '101.01', 'numero_cuenta' => '101-01', 'nombre' => 'Caja', 'naturaleza' => 'D'],
            ['codigo_agrupador' => '102.01', 'numero_cuenta' => '102-01', 'nombre' => 'Bancos nacionales', 'naturaleza' => 'D'],
            ['codigo_agrupador' => '105.01', 'numero_cuenta' => '105-01', 'nombre' => 'Clientes nacionales', 'naturaleza' => 'D'],
            ['codigo_agrupador' => '201.01', 'numero_cuenta' => '201-01', 'nombre' => 'Proveedores nacionales', 'naturaleza' => 'A'],
            ['codigo_agrupador' => '401.01', 'numero_cuenta' => '401-01', 'nombre' => 'Ingresos por ventas', 'naturaleza' => 'A'],
            ['codigo_agrupador' => '601.01', 'numero_cuenta' => '601-01', 'nombre' => 'Gastos generales', 'naturaleza' => 'D'],
            ['codigo_agrupador' => '118.01', 'numero_cuenta' => '118-01', 'nombre' => 'IVA acreditable pagado', 'naturaleza' => 'D'],
            ['codigo_agrupador' => '208.01', 'numero_cuenta' => '208-01', 'nombre' => 'IVA trasladado cobrado', 'naturaleza' => 'A'],
        ];

        foreach ($accounts as $a) {
            Account::updateOrCreate(
                ['client_id' => $client->id, 'numero_cuenta' => $a['numero_cuenta']],
                $a + ['nivel' => 2],
            );
        }
    }
}

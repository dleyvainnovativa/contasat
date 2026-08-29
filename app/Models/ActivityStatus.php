<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual tag / "No aplica" toggle for one activity in the Calendario de
 * actividades, scoped to a client + period. See the migration for the storage
 * contract and ActivityCalendarService for how rows resolve into a status.
 */
class ActivityStatus extends Model
{
    protected $fillable = [
        'client_id', 'period_id', 'activity_key', 'manual_status', 'enabled', 'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /** The four possible resolved states. */
    public const STATUS_REALIZADA  = 'realizada';
    public const STATUS_EN_PROCESO = 'en_proceso';
    public const STATUS_PENDIENTE  = 'pendiente';
    public const STATUS_NO_APLICA  = 'no_aplica';

    /** Statuses an accountant may set by hand (No aplica is a separate toggle). */
    public const MANUAL_STATUSES = [
        self::STATUS_REALIZADA,
        self::STATUS_EN_PROCESO,
        self::STATUS_PENDIENTE,
    ];

    /**
     * The 11 monthly activities, in display order. Estado de cuenta is stored as
     * two keys (solicitud + conciliacion) but shares a display group.
     *
     * mode:  'auto'   → status derived from existing data (manual tag still overrides)
     *        'manual' → accountant tags it; no auto detection
     * group: label used to visually cluster rows (e.g. the two Estado de cuenta rows)
     * sat:   external SAT URL for link-out activities (32D, Constancia)
     */
    public const ACTIVITIES = [
        'op_32d' => [
            'label' => 'Opinión de Cumplimiento 32D',
            'mode'  => 'manual',
            'group' => null,
            'sat'   => 'https://loginda.siat.sat.gob.mx/nidp/app/login?id=ciec&sid=0&option=credential&sid=0',
        ],
        'constancia' => [
            'label' => 'Constancia Fiscal (RFC)',
            'mode'  => 'manual',
            'group' => null,
            'sat'   => 'https://login.siat.sat.gob.mx/nidp/idff/sso?id=mat-ptsc-totp_Aviso&sid=0&option=credential',
        ],
        'descarga_xml' => [
            'label' => 'Descarga de XML',
            'mode'  => 'auto',
            'group' => null,
        ],
        'clasificacion_xml' => [
            'label' => 'Clasificación de XML',
            'mode'  => 'auto',
            'group' => null,
        ],
        'edo_cuenta_solicitud' => [
            'label' => 'Solicitud',
            'mode'  => 'manual',
            'group' => 'Estado de cuenta',
        ],
        'conciliacion' => [
            'label' => 'Conciliación',
            'mode'  => 'auto',
            'group' => 'Estado de cuenta',
        ],
        'presentacion_dyp' => [
            'label' => 'Presentación de DYP',
            'mode'  => 'manual',
            'group' => null,
        ],
        'pago_declaracion' => [
            'label' => 'Pago de la declaración',
            'mode'  => 'manual',
            'group' => null,
        ],
        'diot' => [
            'label' => 'Presentación DIOT',
            'mode'  => 'manual',
            'group' => null,
        ],
        'econtabilidad' => [
            'label' => 'Envío de e.contabilidad',
            'mode'  => 'manual',
            'group' => null,
        ],
        'expediente_fiscal' => [
            'label' => 'Expediente Fiscal',
            'mode'  => 'manual',
            'group' => null,
        ],
    ];

    public static function isValidKey(string $key): bool
    {
        return array_key_exists($key, self::ACTIVITIES);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

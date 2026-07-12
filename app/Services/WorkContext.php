<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Period;
use Illuminate\Support\Facades\Session;

/**
 * The "active client + active period" context. Set once, carried through the
 * session, and read by every screen that operates on a specific client/period.
 *
 * Registered as a singleton (see AppServiceProvider) so any controller/view can
 * resolve the current working context without re-querying the session each time.
 *
 * Usage:
 *   app(WorkContext::class)->setClient($client);
 *   $client = app(WorkContext::class)->client();
 */
class WorkContext
{
    private const CLIENT_KEY = 'work.client_id';
    private const PERIOD_KEY = 'work.period_id';

    private ?Client $client = null;
    private ?Period $period = null;
    private bool $loaded = false;

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        if ($id = Session::get(self::CLIENT_KEY)) {
            $this->client = Client::find($id);
        }

        if ($id = Session::get(self::PERIOD_KEY)) {
            $this->period = Period::with('client')->find($id);

            // Keep client and period coherent: period wins if both are set.
            if ($this->period && (! $this->client || $this->client->id !== $this->period->client_id)) {
                $this->client = $this->period->client;
                Session::put(self::CLIENT_KEY, $this->client->id);
            }
        }

        $this->loaded = true;
    }

    public function client(): ?Client
    {
        $this->load();

        return $this->client;
    }

    public function period(): ?Period
    {
        $this->load();

        return $this->period;
    }

    public function hasClient(): bool
    {
        return $this->client() !== null;
    }

    public function hasPeriod(): bool
    {
        return $this->period() !== null;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
        $this->loaded = true;
        Session::put(self::CLIENT_KEY, $client->id);

        // Changing client invalidates a period that no longer belongs to it.
        if ($this->period && $this->period->client_id !== $client->id) {
            $this->clearPeriod();
        }
    }

    public function setPeriod(Period $period): void
    {
        $this->period = $period;
        $this->client = $period->client;
        $this->loaded = true;
        Session::put(self::PERIOD_KEY, $period->id);
        Session::put(self::CLIENT_KEY, $period->client_id);
    }

    public function clearPeriod(): void
    {
        $this->period = null;
        Session::forget(self::PERIOD_KEY);
    }

    public function clear(): void
    {
        $this->client = null;
        $this->period = null;
        Session::forget([self::CLIENT_KEY, self::PERIOD_KEY]);
    }
}

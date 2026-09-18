<?php

declare(strict_types=1);

namespace Shanginn\Jev\Http;

use Amp\Cancellation;

interface Transport
{
    public function send(string $json, ?Cancellation $cancellation = null): HttpResponse;
}

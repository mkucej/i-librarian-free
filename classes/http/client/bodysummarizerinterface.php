<?php

namespace Librarian\Http\Client;

use Librarian\Http\Psr\Message\MessageInterface;

interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}

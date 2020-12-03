<?php

namespace Librarian\Http\Client;

use Librarian\Http\Client\Psr7\Message;
use Librarian\Http\Psr\Message\MessageInterface;

final class BodySummarizer implements BodySummarizerInterface
{
    /**
     * @var int|null
     */
    private $truncateAt;

    public function __construct(int $truncateAt = null)
    {
        $this->truncateAt = $truncateAt;
    }

    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string
    {
        return $this->truncateAt === null
            ? Message::bodySummary($message)
            : Message::bodySummary($message, $this->truncateAt);
    }
}

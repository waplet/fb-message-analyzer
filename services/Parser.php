<?php

namespace w\MessageParser\Services;

use Carbon\Carbon;
use FluentDOM\Loader\Options;
use w\MessageParser\Structures\Message;
use w\MessageParser\Structures\Texts\Media;
use w\MessageParser\Structures\Texts\Reaction;
use w\MessageParser\Structures\Texts\Text;
use w\MessageParser\Structures\Texts\TextInterface;
use w\MessageParser\Structures\Thread;

class Parser
{
    /**
     * @var \FluentDOM\DOM\Document
     */
    private $document;

    /**
     * @var int
     */
    private $year;

    /**
     * @var array
     */
    private $participants = [];

    /**
     * @var int
     */
    private $limit = -1;

    /**
     * @var int
     */
    private $offset = 0;

    public function __construct(string $file, int $year = 2017)
    {
        $this->document = \FluentDOM::load(
            $file,
            'text/html',
            [Options::ALLOW_FILE => true]
        );
        $this->year = $year;
    }

    public function setLimit(int $limit): Parser
    {
        $this->limit = $limit;

        return $this;
    }

    public function setOffset(int $offset): Parser
    {
        $this->offset = $offset;

        return $this;
    }

    public function setAuthor(string $author): Parser
    {
        $this->participants[] = $author;

        return $this;
    }

    public function parseThread(): Thread
    {
        $this->participants = array_merge($this->participants, $this->getParticipants());

        $thread = new Thread();
        $thread->participants = $this->participants;
        $thread->messages = $this->getMessages();

        return $thread;
    }

    private function getParticipants(): array
    {
        $threadRaw = $this->document->querySelector('.thread');
        $text = $threadRaw->childNodes->item(1)->textContent;

        $text = trim($text);
        $text = str_replace('Participants: ', '', $text);

        return explode(', ', $text);
    }

    private function getMessages(): array
    {
        $messageList = $this->document->querySelectorAll('.message');

        $messages = collect([]);
        $hiddenMessages = collect([]);
        for ($i = 0; $i < $messageList->length; $i++) {

            $messageNode = $messageList->item($i);

            if (!$this->isMessageNodeAcceptable($messageNode)) {
                continue;
            }

            if ($this->limit > -1 && $messages->count() + $hiddenMessages->count() >= $this->limit) {
                break;
            }

            if ($this->offset > $hiddenMessages->count()) {
                $hiddenMessages->push(1);
                continue;
            }

            $nextMessageNode = $messageList->item($i + 1);

            $message = $this->parseMessage($messageNode, $nextMessageNode);
            $messages->push($message);
        }

        return $messages->all();
    }

    private function isMessageNodeAcceptable(\DOMElement $messageNode): bool
    {
        // Thursday, 12 April 2018 at 23:19 EDT
        $date = $this->getMessageNodeDate($messageNode);

        $carbonDate = getCarbon($date);

        return $carbonDate->year === $this->year;
    }

    private function getMessageNodeDate(\DOMElement $messageNode): string
    {
        $dateNode = $messageNode->childNodes->item(0)
            ->childNodes->item(1);

        return $dateNode->textContent;
    }

    private function parseMessage(\DOMElement $messageNode, \DOMElement $nextMessageNode = null): Message
    {
        $message = new Message();
        $message->author = $this->getMessageNodeAuthor($messageNode);
        $message->timestamp = $this->getCarbonDate($this->getMessageNodeDate($messageNode))->timestamp;

        $texts = collect([]);
        $reactions = collect([]);

        $sibling = $messageNode->nextSibling;

        while ($sibling && $sibling !== $nextMessageNode) {
            if ($this->shouldSkipText($sibling)) {
                $sibling = $sibling->nextSibling;
                continue;
            }

            if ($this->isReactionNode($sibling)) {
                $reaction = $this->parseReactionNode($sibling);
                $reactions->push($reaction);
            } else {
                $text = $this->parseTextNode($sibling);
                $texts->push($text);
            }

            $sibling = $sibling->nextSibling;
        }

        $message->texts = $texts->all();
        $message->reactions = $reactions->all();

        return $message;
    }

    private function getMessageNodeAuthor(\DOMElement $messageNode): string
    {
        $authorNode = $messageNode->childNodes->item(0)
            ->childNodes->item(0);

        return $authorNode->textContent;
    }

    private function getCarbonDate(string $date): Carbon
    {
        return Carbon::createFromFormat('l, d F Y \a\t H:i T', $date);
    }

    private function shouldSkipText(\DOMElement $textNode): bool
    {
        // Skip empty messages and those without child (plain empty messages)
        return empty(trim($textNode->textContent)) && $textNode->childNodes->length == 0;
    }

    private function isReactionNode(\DOMElement $textNode): bool
    {
        return $textNode->getAttribute('class') === 'meta';
    }

    private function parseReactionNode(\DOMElement $textNode): Reaction
    {
        $reaction = new Reaction();
        $reaction->content = trim(str_replace($this->participants, '', $textNode->textContent));
        $reaction->author = trim(str_replace($reaction->content, '', $textNode->textContent));

        return $reaction;
    }

    private function parseTextNode(\DOMElement $textNode): TextInterface
    {
        if ($this->isImageNode($textNode) || $this->isVideoNode($textNode)) {
            $text = new Media();
            $text->url = $this->getMediaUrlFromNode($textNode);
        } else {
            $text = new Text();
            $text->content = trim($textNode->textContent);
        }

        return $text;
    }

    private function isImageNode(\DOMElement $textNode): bool
    {
        return $this->isNodeWithType($textNode, 'img');
    }

    private function isVideoNode(\DOMElement $textNode): bool
    {
        return $this->isNodeWithType($textNode, 'video');
    }

    private function isNodeWithType(\DOMElement $textNode, string $tag): bool
    {
        $child = $textNode->childNodes->item(0);

        if (!$child) {
            return false;
        }

        if ($child->tagName !== $tag) {
            return false;
        }

        return true;
    }

    private function getMediaUrlFromNode(\DOMElement $textNode): string
    {
        $child = $textNode->childNodes->item(0);

        return $child->getAttribute('src');
    }
}
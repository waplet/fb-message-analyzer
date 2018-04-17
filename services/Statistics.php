<?php

namespace w\MessageParser\Services;

use Illuminate\Support\Collection;
use w\MessageParser\Structures\Message;
use w\MessageParser\Structures\Texts\Reaction;
use w\MessageParser\Structures\Thread;

class Statistics
{
    /**
     * @var Thread
     */
    private $thread;

    /**
     * @var Message[]
     */
    private $messages;

    public function __construct(Thread $thread)
    {
        $this->thread = $thread;
        $this->messages = $thread->messages;
    }

    public function getTotalMessages(): int
    {
        return collect($this->messages)
            ->reduce(function (int $count, Message $message): int {
                return $count + $message->getMessageCount();
            }, 0);
    }

    public function getMessageCountWithoutText(): int
    {
        return collect($this->messages)
            ->filter(function (Message $message): int {
                return $message->getMessageCount() === 0;
            })->count();
    }

    public function getWordCount(): int
    {
        return collect($this->messages)
            ->reduce(function (int $count, Message $message): int {
                return $count + $message->getWordCount();
            }, 0);
    }

    /**
     * @return array ['day' => string, 'messages' => int]
     */
    public function getMostActiveDay(): array
    {
        return collect($this->messages)
            ->groupBy(function (Message $message): string {
                return $message->getCarbon()->format('Y-m-d');
            })
            ->sortByDesc(function (Collection $messages): int {
                return collect($messages)->reduce(function (int $count, Message $message): int {
                    return $count + $message->getMessageCount();
                }, 0);
            })
            ->map(function (Collection $messages, string $date): array {
                return [
                    'date' => $date,
                    'count' => $messages->reduce(function (int $count, Message $message): int {
                        return $count + $message->getMessageCount();
                    }, 0),
                ];
            })
            ->first();
    }

    public function getMessagesSentGroupedByName(): array
    {
        return collect($this->messages)
            ->groupBy(function (Message $message): string {
                return $message->author;
            })
            ->map(function (Collection $messages) {
                return collect($messages)->reduce(function (int $count, Message $message): int {
                    return $count + $message->getMessageCount();
                }, 0);
            })
            ->toArray();
    }

    public function getMessagesSentGroupedByDayAndName(): array
    {
        return collect($this->messages)
            ->groupBy(function (Message $message): string {
                return $message->getCarbon()->format('Y-m-d');
            })
            ->map(function (Collection $messages) {
                return $messages
                    ->groupBy(function (Message $message): string {
                        return $message->author;
                    })
                    ->map(function (Collection $messages): int {
                        return collect($messages)->reduce(function (int $count, Message $message): int {
                            return $count + $message->getMessageCount();
                        }, 0);
                    });
            })
            ->toArray();
    }

    public function getMessagesSentGroupedByMonthAndName(): array
    {
        return collect($this->messages)
            ->groupBy(function (Message $message): string {
                return $message->getCarbon()->format('Y-m');
            })
            ->map(function (Collection $messages) {
                return $messages
                    ->groupBy(function (Message $message): string {
                        return $message->author;
                    })
                    ->map(function (Collection $messages): int {
                        return collect($messages)->reduce(function (int $count, Message $message): int {
                            return $count + $message->getMessageCount();
                        }, 0);
                    });
            })
            ->toArray();
    }

    public function getMessagesSentGroupedByWeekDayAndName(): array
    {
        return collect($this->messages)
            ->groupBy(function (Message $message): string {
                return $message->getCarbon()->format('N - l');
            })
            ->map(function (Collection $messages) {
                return $messages
                    ->groupBy(function (Message $message): string {
                        return $message->author;
                    })
                    ->map(function (Collection $messages): int {
                        return collect($messages)->reduce(function (int $count, Message $message): int {
                            return $count + $message->getMessageCount();
                        }, 0);
                    });
            })
            ->sortKeys()
            ->toArray();
    }

    public function getMessagesSentGroupedByHourAndName(): array
    {
        return collect($this->messages)
            ->groupBy(function (Message $message): string {
                return $message->getCarbon()->format('H');
            })
            ->map(function (Collection $messages) {
                return $messages
                    ->groupBy(function (Message $message): string {
                        return $message->author;
                    })
                    ->map(function (Collection $messages): int {
                        return collect($messages)->reduce(function (int $count, Message $message): int {
                            return $count + $message->getMessageCount();
                        }, 0);
                    });
            })
            ->sortKeys()
            ->toArray();
    }

    public function getReactionCountsGroupedByAuthor(): array
    {
        return collect($this->messages)
            ->map(function (Message $message): array {
                return $message->reactions;
            })
            ->flatten(1)
            ->groupBy(function (Reaction $reaction): string {
                return $reaction->author;
            })
            ->map(function (Collection $reactions) {
                return $reactions->count();
            })
            ->toArray();
    }

    public function getEmojiCountsGroupedByAuthor(): array
    {
        return collect($this->messages)
            ->groupBy(function (Message $message): string {
                return $message->author;
            })
            ->map(function (Collection $messages) {
                return $messages
                    ->map(function (Message $message) {
                        return $message->getEmojiTexts();
                    })
                    ->reduce(function (int $count, array $texts): int {
                        return $count + count($texts);
                    }, 0);
            })
            ->toArray();
    }

    public function getWordCountsGroupedByAuthor(): array
    {
        return collect($this->messages)
            ->groupBy(function (Message $message): string {
                return $message->author;
            })
            ->map(function (Collection $messages) {
                return $messages->reduce(function (int $count, Message $message): int {
                    return $count + $message->getWordCount();
                }, 0);
            })
            ->toArray();
    }

    public function getMostUsedEmojis(): array
    {
        return collect($this->messages)
            ->map(function (Message $message): array {
                return $message->getMessageEmojis();
            })
            ->flatten(1)
            ->groupBy(function (string $emoji) {
                return $emoji;
            })
            ->map(function (Collection $emojis) {
                return $emojis->count();
            })
            ->sortByDesc(function (int $emojiCounts) {
                return $emojiCounts;
            })
            ->take(10)
            ->toArray();
    }

    public function getMostUsedWords(): array
    {
        return collect($this->messages)
            ->map(function (Message $message) {
                return $message->getWords();
            })
            ->flatten(1)
            ->filter(function (string $word): bool {
                return mb_strlen($word) > 4;
            })
            ->groupBy(function (string $word): string {
                return mb_strtolower($word);
            })
            ->map(function (Collection $words): int {
                return $words->count();
            })
            ->sortByDesc(function (int $wordCounts): int {
                return $wordCounts;
            })
            ->take(10)
            ->toArray();
    }
}
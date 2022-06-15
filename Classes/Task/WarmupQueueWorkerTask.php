<?php

declare(strict_types=1);

namespace Smic\PageWarmup\Task;

use GuzzleHttp\Exception\ClientException;
use Smic\PageWarmup\Service\QueueService;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\ProgressProviderInterface;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

class WarmupQueueWorkerTask extends AbstractTask implements ProgressProviderInterface
{
    private int $timeLimit = 60;

    public function execute(): bool
    {
        $this->workThroughQueueWithTimeLimit($this->timeLimit);
        return true;
    }

    public function getTimeLimit(): int
    {
        return $this->timeLimit;
    }

    public function setTimeLimit(int $timeLimit): void
    {
        $this->timeLimit = $timeLimit;
    }

    public function workThroughQueueWithTimeLimit(int $seconds): void
    {
        $queueService = GeneralUtility::makeInstance(QueueService::class);
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
        $end = time() + $seconds;

        foreach ($queueService->provide() as $url) {
            if (time() >= $end) {
                return;
            }
            try {
                $requestFactory->request($url);
            } catch (ClientException $e) {
                // ignore
            }
        }
    }

    public function getProgress(): float
    {
        $queueService = GeneralUtility::makeInstance(QueueService::class);
        return max(0.01, $queueService->getProgress());
    }

    public function getAdditionalInformation(): string
    {
        $queueService = GeneralUtility::makeInstance(QueueService::class);
        $totalCount = $queueService->getTotalCount();
        if ($totalCount === 0) {
            return '';
        }
        return 'Warmed up ' . number_format($queueService->getDoneCount()) . ' of ' . number_format($queueService->getTotalCount()) . ' URLs that are in the current queue.';
    }
}

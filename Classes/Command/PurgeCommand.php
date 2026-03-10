<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Command;

use LiteSpeed\Lscache\Configuration\ExtensionConfig;
use LiteSpeed\Lscache\Service\PurgeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Site\Exception\SiteNotFoundException;

#[AsCommand(
    name: 'lscache:purge',
    description: 'Purge LiteSpeed Cache via the internal purge endpoint.',
)]
final class PurgeCommand extends Command
{
    public function __construct(
        private readonly ExtensionConfig $config,
        private readonly PurgeService $purgeService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('site', null, InputOption::VALUE_OPTIONAL, 'Limit purge to a site identifier.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->config->isEnabled()) {
            $output->writeln('<comment>LSCache extension is disabled.</comment>');
            return Command::SUCCESS;
        }

        if ($this->config->getPurgeToken() === '') {
            $output->writeln('<error>Purge token is not configured.</error>');
            return Command::FAILURE;
        }

        $site = (string)$input->getOption('site');
        try {
            if ($site !== '') {
                $result = $this->purgeService->purgeSiteByIdentifier($site);
            } else {
                $result = $this->purgeService->purgeAllSites();
            }
        } catch (SiteNotFoundException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        foreach ($result['errors'] as $error) {
            $output->writeln('<error>' . $error . '</error>');
        }

        $output->writeln(sprintf('Purged LSCache for %d site(s), %d error(s).', $result['success'], $result['failed']));

        return $result['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}

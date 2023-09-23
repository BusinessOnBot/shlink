<?php

declare(strict_types=1);

namespace Shlinkio\Shlink\CLI\Command\Api;

use Cake\Chronos\Chronos;
use Shlinkio\Shlink\CLI\ApiKey\RoleResolverInterface;
use Shlinkio\Shlink\CLI\Util\ExitCode;
use Shlinkio\Shlink\CLI\Util\ShlinkTable;
use Shlinkio\Shlink\Rest\ApiKey\Model\ApiKeyMeta;
use Shlinkio\Shlink\Rest\ApiKey\Role;
use Shlinkio\Shlink\Rest\Entity\ApiKey;
use Shlinkio\Shlink\Rest\Service\ApiKeyServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Shlinkio\Shlink\Core\arrayToString;
use function sprintf;

class GenerateKeyCommand extends Command
{
    public const NAME = 'api-key:generate';

    public function __construct(
        private readonly ApiKeyServiceInterface $apiKeyService,
        private readonly RoleResolverInterface $roleResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $authorOnly = Role::AUTHORED_SHORT_URLS->paramName();
        $domainOnly = Role::DOMAIN_SPECIFIC->paramName();
        $noOrphanVisits = Role::NO_ORPHAN_VISITS->paramName();

        $help = <<<HELP
        The <info>%command.name%</info> generates a new valid API key.

            <info>%command.full_name%</info>

        You can optionally set its name for tracking purposes with <comment>--name</comment> or <comment>-m</comment>:

            <info>%command.full_name% --name Alice</info>

        You can optionally set its expiration date with <comment>--expiration-date</comment> or <comment>-e</comment>:

            <info>%command.full_name% --expiration-date 2020-01-01</info>

        You can also set roles to the API key:

            * Can interact with short URLs created with this API key: <info>%command.full_name% --{$authorOnly}</info>
            * Can interact with short URLs for one domain: <info>%command.full_name% --{$domainOnly}=example.com</info>
            * Cannot see orphan visits: <info>%command.full_name% --{$noOrphanVisits}</info>
            * All: <info>%command.full_name% --{$authorOnly} --{$domainOnly}=example.com --{$noOrphanVisits}</info>
        HELP;

        $this
            ->setName(self::NAME)
            ->setDescription('Generate a new valid API key.')
            ->addOption(
                'name',
                'm',
                InputOption::VALUE_REQUIRED,
                'The name by which this API key will be known.',
            )
            ->addOption(
                'expiration-date',
                'e',
                InputOption::VALUE_REQUIRED,
                'The date in which the API key should expire. Use any valid PHP format.',
            )
            ->addOption(
                $authorOnly,
                'a',
                InputOption::VALUE_NONE,
                sprintf('Adds the "%s" role to the new API key.', Role::AUTHORED_SHORT_URLS->value),
            )
            ->addOption(
                $domainOnly,
                'd',
                InputOption::VALUE_REQUIRED,
                sprintf(
                    'Adds the "%s" role to the new API key, with the domain provided.',
                    Role::DOMAIN_SPECIFIC->value,
                ),
            )
            ->addOption(
                $noOrphanVisits,
                'o',
                InputOption::VALUE_NONE,
                sprintf('Adds the "%s" role to the new API key.', Role::NO_ORPHAN_VISITS->value),
            )
            ->setHelp($help);
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $expirationDate = $input->getOption('expiration-date');

        $apiKey = $this->apiKeyService->create(ApiKeyMeta::fromParams(
            name: $input->getOption('name'),
            expirationDate: isset($expirationDate) ? Chronos::parse($expirationDate) : null,
            roleDefinitions: $this->roleResolver->determineRoles($input),
        ));

        $io = new SymfonyStyle($input, $output);
        $io->success(sprintf('Generated API key: "%s"', $apiKey->toString()));

        if (! ApiKey::isAdmin($apiKey)) {
            ShlinkTable::default($io)->render(
                ['Role name', 'Role metadata'],
                $apiKey->mapRoles(fn (Role $role, array $meta) => [$role->value, arrayToString($meta, 0)]),
                null,
                'Roles',
            );
        }

        return ExitCode::EXIT_SUCCESS;
    }
}

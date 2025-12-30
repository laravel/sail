<?php

namespace Laravel\Sail\Console\Concerns;

trait InteractsWithDockerPrompts
{
    /**
     * Gather the desired Sail services using an interactive prompt.
     *
     * @return array
     */
    protected function gatherEnvironmentsInteractively()
    {
        $defaultEnvs = explode(',', config('sail.build.environments')) ?? $this->defaultEnvs;
        $environments = array_unique(array_merge($this->defaultEnvs, $this->environments));

        sort($environments);
        if (function_exists('\Laravel\Prompts\multiselect')) {
            return \Laravel\Prompts\multiselect(
                label: 'Which environments would you like to use?',
                options: $environments,
                default: $defaultEnvs,
                scroll: count($environments) > 20 ? 15 : count($environments),
                required: true,
            );
        }

        return $this->choice('Which environments would you like to use?', $$environments, 0, null, true);
    }

    /**
     * Gather the desired Sail repositories using an interactive prompt.
     *
     * @return string
     */
    protected function gatherRepositoryInteractively(array $environments)
    {
        if (! in_array('production', $environments)) {
            return 'none';
        }
        if (function_exists('\Laravel\Prompts\select')) {
            $repositories = \Laravel\Prompts\select(
                label: 'Which repository would you like to use?',
                options: $this->repositories,
                default: 'ghcr.io',
                scroll: count($this->repositories) > 20 ? 15 : count($this->repositories),
                required: true,
            );
        } else {
            $repositories = $this->choice('Which repository would you like to use?', $this->repositories, 0, null, false);
        }

        if ($repositories === 'none') {
            $this->useRepository = false;
        } else {
            $this->useRepository = true;
            $this->choosePush();
            $this->gatherOrtanizationNameInteractively();
        }

        return $repositories;
    }

    /**
     * Get a confirmation from the user to push the images to the repository.
     *
     * @return array
     */
    protected function choosePush()
    {
        if (function_exists('\Laravel\Prompts\confirm')) {
            $this->push = \Laravel\Prompts\confirm(
                label: 'Would you like to push the images to the repository?',
                default: config('sail.build.push', false),
            );
        } else {
            $this->push = $this->confirm('Would you like to push the images to the repository?', config('sail.build.push', false));
        }
    }

    /**
     * Gather the desired Sail domains using an interactive prompt.
     *
     * @return array
     */
    protected function gatherDeploymentDomainsInteractively()
    {
        $this->deploymentDomains = explode(',', config('sail.deploy.domains'));
        $domains = $this->deploymentDomains;
        $domains = array_unique(array_merge(['** Add new domain **'], $this->deploymentDomains));

        if (function_exists('\Laravel\Prompts\multiselect')) {
            $selected = \Laravel\Prompts\multiselect(
                label: 'Which repository would you like to use?',
                options: $domains,
                default: $this->deploymentDomains,
                scroll: count($domains) > 20 ? 15 : count($domains),
                required: true,
            );
        } else {
            $selected = $this->choice('Which repository would you like to use?', $domains, 0, null, false);
        }

        if (in_array('** Add new domain **', $selected)) {
            $this->deploymentDomains = array_diff($selected, ['** Add new domain **']);

            do {
                $this->gatherNewDomainInteractively();
            } while ($this->moreDomains());
        } else {
            $this->deploymentDomains = $selected;
        }

        return $this->deploymentDomains;
    }

    /**
     * Check if user wants to add more domains.
     *
     * @return bool
     */
    protected function moreDomains()
    {
        if (function_exists('\Laravel\Prompts\confirm')) {
            return \Laravel\Prompts\confirm(
                label: 'Would you like to add another domain?',
                default: false,
            );
        } else {
            return $this->confirm('Would you like to add another domain?', false);
        }
    }

    /**
     * Gather the desired Sail domains using an interactive prompt.
     *
     * @return string
     */
    protected function gatherNewDomainInteractively(): void
    {
        if (function_exists('\Laravel\Prompts\text')) {
            $this->deploymentDomains[] = \Laravel\Prompts\text(
                label: 'What is the new domain?',
                required: true,
            );
        } else {
            $this->deploymentDomains[] = $this->ask('What is the new domain?');
        }
    }

    /**
     * Gather the desired Sail architectures using an interactive prompt.
     *
     * @return array
     */
    protected function gatherArchitecturesInteractively()
    {
        $defaultArchs = explode(',', config('sail.build.architectures')) ?? $this->defaultArchs;
        $archs = array_unique(array_merge($this->defaultArchs, $this->archs));

        sort($archs);
        if (function_exists('\Laravel\Prompts\multiselect')) {
            return \Laravel\Prompts\multiselect(
                label: 'Which architectures would you like to use?',
                options: $archs,
                default: $defaultArchs,
                scroll: count($archs) > 20 ? 15 : count($archs),
                required: true,
            );
        }

        return $this->choice('Which architectures would you like to use?', $archs, 0, null, true);
    }

    /**
     * Gather the desired Sail organization name using an interactive prompt.
     *
     * @return ?string
     */
    protected function gatherOrtanizationNameInteractively()
    {
        if ($this->organization) {
            return;
        }

        if (function_exists('\Laravel\Prompts\input')) {
            $this->organization = \Laravel\Prompts\text(
                label: 'What is the name of your repository organization?',
                default: 'reyemtech',
                required: true,
            );
        } else {
            $this->organization = $this->ask(
                'What is the name of your organization?',
                'reyemtech'
            );
        }

        return $this->organization;
    }
}


<?php

namespace App\Console\Commands;

use App\Models\LawArticle;
use App\Models\LegalReference;
use Illuminate\Console\Command;

class GenerateSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:slugs {--force : Force regenerate existing slugs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate slugs for legal references and law articles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting slug generation...');

        $this->generateLegalReferenceSlugs();
        $this->generateLawArticleSlugs();

        $this->info('Slug generation completed successfully!');

        return 0;
    }

    private function generateLegalReferenceSlugs(): void
    {
        $this->info('Generating slugs for legal references...');

        $query = LegalReference::query();

        if (!$this->option('force')) {
            $query->whereNull('slug');
        }

        $legalReferences = $query->get();

        $bar = $this->output->createProgressBar($legalReferences->count());
        $bar->start();

        foreach ($legalReferences as $legalReference) {
            $legalReference->slug = $legalReference->generateSlug();
            $legalReference->save();
            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info("Generated slugs for {$legalReferences->count()} legal references");
    }

    private function generateLawArticleSlugs(): void
    {
        $this->info('Generating slugs for law articles...');

        $query = LawArticle::with('legalReference');

        if (!$this->option('force')) {
            $query->whereNull('slug');
        }

        $lawArticles = $query->get();

        $bar = $this->output->createProgressBar($lawArticles->count());
        $bar->start();

        foreach ($lawArticles as $lawArticle) {
            $lawArticle->slug = $lawArticle->generateSlug();
            $lawArticle->save();
            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info("Generated slugs for {$lawArticles->count()} law articles");
    }
}

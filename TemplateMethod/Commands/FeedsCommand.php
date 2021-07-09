<?php declare(strict_types = 1);

namespace App\Services\Exchanger\Commands;

use App\Services\Exchanger\Contracts\Command;
use App\Services\Exchanger\Models\Feed;
use App\Services\Exchanger\Repositories\FeedsRepository;
use Carbon\Carbon;

/**
 * Class FeedsCommand
 * @package App\Services\Exchanger\Commands
 */
class FeedsCommand extends Command
{
    protected $signature = 'exchanger:feeds';

    protected $description = 'Send feeds to ExpertSender';

    protected $model = Feed::class;

    protected $import_type = 'table';

    /** @var FeedsRepository */
    protected $feeds_repository;

    /**
     * FeedsCommand constructor.
     *
     * @param  FeedsRepository  $repository
     */
    public function __construct(FeedsRepository $repository)
    {
        parent::__construct();

        $this->feeds_repository = $repository;
    }

    protected function collect(): void
    {
        /**
         * здесь собираем данные и кладем их в коллекцию $articles
         *
         * @var \Illuminate\Support\Collection $articles
         */

        $this->silentMessage(
            sprintf("%d articles stored/updated", $articles->count())
        );
    }
}

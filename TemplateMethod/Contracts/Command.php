<?php declare(strict_types = 1);

namespace App\Services\Exchanger\Contracts;

use App\Models\Exchanger as ExchangerLogs;
use App\Services\Exchanger\Exchanger;
use App\Services\Exchanger\ExpertSender;
use App\Services\Exchanger\Repositories\ExchangerRepository;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Throwable;
use UnhandledMatchError;

/**
 * Class Command
 * @package App\Services\Exchanger\Contracts
 */
abstract class Command extends \Illuminate\Console\Command
{
    /** @var UuidInterface */
    protected $uuid;

    /** @var Exchanger */
    protected $exchanger;

    /** @var ExchangerRepository */
    protected $repository;

    /** @var ExpertSender */
    protected $expertsender;

    /** @var string */
    protected $filename;

    /** @var string */
    protected $model;

    /** @var string */
    protected $import_type;

    /** @var bool */
    protected $stopped = false;

    /** @var string */
    public static $synced_at;

    /**
     * Command constructor.
     */
    public function __construct()
    {
        parent::__construct();

        self::$synced_at = now()->format('Y-m-d H:i:s');

        // задаем уникальный ид команды
        $this->uuid = Str::uuid();

        // если надо вызвать только один из шагов
        $this->addOption('only', null, InputOption::VALUE_OPTIONAL);

        // если не надо спамить в слак
        $this->addOption('silent', null, InputOption::VALUE_OPTIONAL);

        if (empty($this->model)) {
            throw new InvalidArgumentException('Undefined model');
        }

        if (empty($this->import_type)) {
            throw new InvalidArgumentException('Undefined import type');
        }

        $this->filename = (new $this->model)->getTable();

        $this->exchanger    = resolve(Exchanger::class);
        $this->repository   = resolve(ExchangerRepository::class);
        $this->expertsender = resolve(ExpertSender::class);
    }

    // собственно абстрактный метод
    final public function handle()
    {
        $only = $this->option('only');

        $this->message(
            message: sprintf("Starting `%s` command", $this->signature),
            context: collect($this->options())->only('only')->filter()->toArray(),
            action: 'command.started',
        );

        if ($only === null || $only === 'collect') {
            $time = now();
            $this->message(
                message: 'Collecting started',
                action: 'task.started',
                silent: true,
            );

            try {
                $this->collect();
            } catch (Throwable $e) {
                $this->message(
                    message: $e->getMessage(),
                    action: 'task.error',
                );
            }

            $this->message(
                message: sprintf("Completed in %d seconds", $time->diffInSeconds()),
                action: 'task.completed',
                silent: true,
            );
        }

        if (! $this->isStopped() && ($only === null || $only === 'store')) {
            $time = now();
            $this->message(
                message: 'Storing started',
                action: 'task.started',
                silent: true,
            );

            try {
                $this->store();
            } catch (Throwable $e) {
                $this->message(
                    message: $e->getMessage(),
                    action: 'task.error',
                );
            }

            $this->message(
                message: sprintf("Completed in %d seconds", $time->diffInSeconds()),
                action: 'task.completed',
                silent: true,
            );
        }

        if (! $this->isStopped() && ($only === null || $only === 'send')) {
            $time = now();
            $this->message(
                message: 'Sending started',
                action: 'task.started',
                silent: true,
            );

            try {
                $this->send();
            } catch (Throwable $e) {
                $this->message(
                    message: $e->getMessage(),
                    action: 'task.error',
                );
            }

            $this->message(
                message: sprintf("Completed in %d seconds", $time->diffInSeconds()),
                action: 'task.completed',
                silent: true,
            );
        }

        $this->message(
            message: sprintf("`%s` command completed", $this->signature),
            action: 'command.completed',
        );
    }

    /**
     * @param  bool  $storage
     * @param  bool  $test
     *
     * @return string
     * @throws Exception
     */
    protected function getFilePath(bool $storage = false, bool $test = false)
    {
        if (empty($this->filename)) {
            throw new Exception('Undefined filename');
        }

        $directory = $test
            ? 'exchanger/test'
            : 'exchanger';

        $filepath = sprintf("%s/%s.csv", $directory, $this->filename);
        if ($storage) {
            return Storage::path($filepath);
        }

        return $filepath;
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function getFilePublicPath()
    {
        $path = $this->getFilePath(
            test: ! app()->environment('production')
        );

        if (! Storage::exists($path)) {
            throw new RuntimeException('File [' . $path . '] not found');
        }

        return asset(
            sprintf("storage/%s", $path)
        );
    }

    /**
     * @param  string  $message
     * @param  array  $context
     * @param  string  $action
     * @param  bool  $silent
     */
    protected function message(string $message, array $context = [], string $action = 'task.comment', bool $silent = false)
    {
        // отправляем сообщение в слак и пишем в журнал
    }

    /**
     * @param  string  $message
     * @param  array  $context
     * @param  string  $action
     */
    protected function silentMessage(string $message, array $context = [], string $action = 'task.comment')
    {
        $this->message($message, $context, $action, true);
    }

    /**
     * Store data in file
     *
     * @throws Exception
     */
    protected function store(): void
    {
        // здесь сохраняем данные в файл
        // логика предопределена в абстракции, но может быть переписана в потомке
    }

    /**
     * Export data to Expert Sender
     *
     * @throws Throwable
     */
    protected function send(): void
    {
        // здесь отправляем данные в очередь
        // логика предопределена в абстракции, но может быть переписана в потомке
    }

    protected function stop()
    {
        $this->stopped = true;

        $this->message('Command aborted');
    }

    /**
     * @return bool
     */
    protected function isStopped()
    {
        return $this->stopped;
    }

    /**
     * Collect data to database
     *
     * // здесь собираем данные
     * для каждой команды - своя логика,
     * поэтому делаем абстрактный метод
     */
    abstract protected function collect(): void;
}

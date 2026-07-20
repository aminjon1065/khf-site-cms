<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\HazardType;
use App\Models\Instruction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class InstructionSeeder extends Seeder
{
    public function run(): void
    {
        $authorId = User::query()->where('email', 'f.nazarov@khf.tj')->value('id');

        $items = [
            ['Землетрясение', 'Заминҷунбӣ', HazardType::Earthquake, 'Действия до, во время и после подземных толчков', '2026-06-12', ContentStatus::Published, true],
            ['Сель и наводнение', 'Сел ва обхезӣ', HazardType::Mudflow, 'Как действовать при угрозе селевого потока', '2026-07-18', ContentStatus::Published, false],
            ['Лавина', 'Тарма', HazardType::Avalanche, 'Безопасность в горах в лавиноопасный период', '2026-07-02', ContentStatus::Published, false],
            ['Пожар', 'Сӯхтор', HazardType::Fire, 'Что делать при возникновении пожара', '2026-05-14', ContentStatus::Published, true],
            ['Сильный ветер', 'Шамоли сахт', HazardType::Wind, 'Меры предосторожности при штормовом ветре', '2026-04-20', ContentStatus::Published, true],
            ['Жара', 'Гармӣ', HazardType::Heat, 'Поведение при аномально высокой температуре', '2026-07-17', ContentStatus::Draft, false],
            ['Мороз', 'Сармо', HazardType::Frost, 'Как уберечься от переохлаждения зимой', '2026-01-10', ContentStatus::Published, true],
            ['Первая помощь', 'Кӯмаки аввалия', null, 'Базовые приёмы до прибытия медиков', '2026-05-20', ContentStatus::Published, true],
            ['Безопасность детей', 'Бехатарии кӯдакон', null, 'Как объяснить детям правила поведения при ЧС', '2026-06-30', ContentStatus::Published, false],
            ['Безопасность в горах', 'Бехатарӣ дар кӯҳҳо', HazardType::Landslide, 'Правила для туристов и альпинистов', '2026-06-05', ContentStatus::Published, false],
            ['Эвакуация', 'Эвакуатсия', null, 'Порядок действий при объявлении эвакуации', '2026-06-30', ContentStatus::Published, false],
            ['Наводнение', 'Обхезӣ', HazardType::Flood, 'Действия при подъёме уровня воды', '2026-07-05', ContentStatus::Published, true],
        ];

        $priorityHazards = [HazardType::Earthquake, HazardType::Mudflow, HazardType::Avalanche];

        foreach ($items as $index => $it) {
            [$nameRu, $nameTg, $hazard, $summaryRu, $updated, $status, $enComplete] = $it;

            $sections = [
                'before' => [
                    'ru' => ['Подготовьте тревожный чемоданчик с документами, водой и аптечкой.', 'Изучите пути эвакуации и места сбора.'],
                    'tg' => ['Ҳуҷҷатҳо, об ва аптечкаро омода созед.'],
                    'en' => $enComplete ? ['Prepare an emergency kit with documents, water and a first-aid kit.'] : [],
                ],
                'during' => [
                    'ru' => ['Сохраняйте спокойствие и следуйте указаниям служб КЧС.', 'Не пользуйтесь лифтом.'],
                    'tg' => ['Оромиро нигоҳ доред ва ба дастури хадамот амал кунед.'],
                    'en' => $enComplete ? ['Stay calm and follow instructions from emergency services.'] : [],
                ],
                'after' => [
                    'ru' => ['Убедитесь в отсутствии угроз, окажите помощь пострадавшим.', 'Сообщите о происшествии по номеру 112.'],
                    'tg' => ['Аз набудани хатар боварӣ ҳосил кунед.'],
                    'en' => $enComplete ? ['Make sure there is no further danger and help the injured.'] : [],
                ],
                'prohibited' => [
                    'ru' => ['Не распространяйте непроверенную информацию.', 'Не возвращайтесь в опасную зону без разрешения.'],
                    'tg' => ['Маълумоти тасдиқнашударо паҳн накунед.'],
                    'en' => $enComplete ? ['Do not spread unverified information.'] : [],
                ],
            ];

            Instruction::updateOrCreate(
                ['slug' => Str::slug($nameRu, '-', 'ru') ?: Str::slug($nameTg)],
                [
                    'name' => ['ru' => $nameRu, 'tg' => $nameTg, 'en' => $enComplete ? $nameRu : ''],
                    'summary' => ['ru' => $summaryRu, 'tg' => $summaryRu, 'en' => $enComplete ? $summaryRu : ''],
                    'hazard_type' => $hazard,
                    'is_priority' => $hazard !== null && in_array($hazard, $priorityHazards, true),
                    'sort' => $index,
                    'sections' => $sections,
                    'status' => $status,
                    'published_at' => $status === ContentStatus::Published ? Carbon::parse($updated) : null,
                    'author_id' => $authorId,
                ],
            );
        }
    }
}

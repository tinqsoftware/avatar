<?php

namespace Database\Seeders;

use App\Models\Avatar;
use App\Services\ConversationTree;
use Illuminate\Database\Seeder;

class AvatarDemoSeeder extends Seeder
{
    public function run(): void
    {
        $avatar = Avatar::updateOrCreate(
            ['slug' => 'ica-demo'],
            [
                'name' => 'Anita Ica Demo',
                'public_title' => 'Gobierno Regional de Ica · Demo',
                'voice_profile' => 'anita',
                'rive_path' => 'assets/avatar/anita.riv',
                'status' => 'draft',
            ],
        );

        $tree = [
            'greeting' => ['variants' => [
                'Hola, soy Anita. Esta es una demostración ficticia de propuestas para el Gobierno Regional de Ica. ¿Sobre qué tema te gustaría conversar?',
                'Bienvenida o bienvenido. Soy Anita y te acompañaré en esta demostración ficticia para Ica. Puedes preguntarme por seguridad, agua, empleo, vías o presupuesto.',
                'Qué gusto conversar contigo. Soy Anita y puedo presentar los ejes ficticios de esta demostración regional.',
                'Estoy lista para ayudarte a recorrer esta propuesta demostrativa. Dime qué inquietud tienes sobre agua, seguridad, empleo, vías o presupuesto.',
                'Hola. En este espacio demo podemos revisar propuestas ficticias y pasar de un tema a otro con calma.',
                'Soy Anita. Recuerda que toda la información que escucharás es demostrativa; elige el asunto que quieres explorar.',
            ]],
            'fallback' => ['variants' => [
                'No encontré ese tema dentro de esta demostración ficticia. Si quieres, pregúntame por agua, seguridad, empleo, vías o presupuesto.',
                'Esa consulta no figura en los ejes demo que tengo disponibles. Puedo orientarte sobre agua, seguridad, empleo, vías y presupuesto.',
                'Para no inventar información, prefiero quedarme en los temas de esta demostración: agua, seguridad, empleo, vías o presupuesto.',
                'No tengo una respuesta demostrativa preparada para ese asunto. Podemos revisar cualquiera de los cinco ejes disponibles.',
                'Ese punto queda fuera de este árbol ficticio. Dime si quieres conversar sobre servicios, seguridad, trabajo, conectividad o presupuesto.',
                'No lo ubiqué entre las propuestas demo. Estoy lista para continuar con agua, seguridad, empleo, vías o presupuesto.',
            ]],
            'connectors' => [
                'queue' => ['variants' => [
                    'Claro, déjame organizar esa consulta.',
                    'Entiendo. Estoy preparando la información demostrativa para ti.',
                    'Dame un momento mientras ordeno los puntos que mencionaste.',
                    'Voy a revisar esa propuesta demo antes de responderte.',
                    'Gracias por la pregunta. Estoy reuniendo las respuestas correspondientes.',
                    'Estoy organizando los temas para responderte con claridad.',
                ]],
                'multi_intro' => ['variants' => [
                    'Veo que conectas varios asuntos importantes. Los revisemos con calma.',
                    'Hay varias inquietudes en tu pregunta. Voy a responderlas una por una.',
                    'Gracias por plantear estos temas juntos. Empecemos por el que mencionaste al inicio.',
                    'Tu consulta reúne distintos frentes de la propuesta demo. Vamos punto por punto.',
                    'Son preguntas relacionadas y vale la pena mirarlas con orden. Comencemos por el primer asunto.',
                    'Tocas más de un eje de esta demostración. Te cuento cada parte de forma seguida.',
                ]],
                'multi_bridge' => ['variants' => [
                    'Respecto al siguiente tema que mencionas, la propuesta demostrativa plantea lo siguiente.',
                    'Pasemos ahora al otro asunto de tu consulta.',
                    'Sobre el siguiente punto que señalaste, esta es la idea central.',
                    'También preguntaste por otro eje. Te lo explico enseguida.',
                    'Mirando la otra parte de tu pregunta, la demostración propone esto.',
                    'Hay un segundo aspecto importante en lo que comentas. Vamos con él.',
                ]],
                'multi_outro' => ['variants' => [
                    'Esos son los puntos principales de los temas que reuniste. Si quieres, puedo ampliar el último asunto.',
                    'Así se conectan estos ejes dentro de la demostración. Dime si quieres profundizar en alguno.',
                    'Con eso quedan cubiertos los temas que mencionaste. Podemos seguir con más detalle sobre el último.',
                    'Esa es la vista general de tu consulta. Estoy lista para continuar desde el punto final.',
                    'Hemos recorrido los asuntos que planteaste. Puedes pedirme ampliar el último cuando quieras.',
                    'Hasta aquí la respuesta demostrativa a tus temas. Seguimos si deseas conocer más del último.',
                ]],
                'continue_last' => ['variants' => [
                    'Claro, ampliemos el último tema que revisamos.',
                    'Sigamos con el punto final de tu consulta anterior.',
                    'Profundicemos un poco más en el último asunto que mencionaste.',
                    'Retomemos el tema más reciente para ver su siguiente parte.',
                    'Vamos a desarrollar la última propuesta que acabamos de revisar.',
                    'Continúo con más detalle sobre el último eje conversado.',
                ]],
            ],
            'topics' => [
                $this->topic('agua', 'Agua y saneamiento', ['agua', 'saneamiento', 'desague', 'riego'], 'priorizar agua segura, mantenimiento preventivo y seguimiento público de cada obra', 'crear un mapa de brechas, coordinar con municipalidades y publicar metas trimestrales', 'abrir mesas vecinales para revisar avances, alertas y prioridades de inversión'),
                $this->topic('seguridad', 'Seguridad ciudadana', ['seguridad', 'delincuencia', 'serenazgo', 'policia'], 'trabajar prevención, coordinación territorial y datos abiertos sobre incidencias', 'identificar puntos críticos, mejorar la iluminación y realizar campañas con organizaciones vecinales', 'convocar seguimientos por provincia y ajustar las acciones según resultados observables'),
                $this->topic('empleo', 'Empleo y emprendimiento', ['empleo', 'trabajo', 'emprendimiento', 'negocio', 'jovenes'], 'conectar capacitación, empleabilidad y pequeños emprendimientos con oportunidades regionales', 'impulsar alianzas con institutos, empresas y municipios, además de ferias transparentes de oportunidades', 'medir inserción laboral, continuidad de negocios y publicar resultados por provincia'),
                $this->topic('vias', 'Vías y conectividad', ['vias', 'carretera', 'camino', 'transporte', 'conectividad'], 'priorizar mantenimiento vial con criterios públicos de seguridad, conectividad y acceso a servicios', 'mantener un inventario de puntos vulnerables, cronogramas visibles y coordinación con provincias', 'evaluar avances físicos y presupuestales con supervisión ciudadana sobre plazos y calidad'),
                $this->topic('presupuesto', 'Presupuesto y participación', ['presupuesto', 'dinero', 'inversion', 'participacion', 'transparencia'], 'explicar el presupuesto regional en lenguaje sencillo y abrir espacios de priorización ciudadana', 'usar tableros públicos, audiencias por provincia y un calendario visible de inversiones', 'incorporar reportes ciudadanos y evaluaciones públicas anuales para mejorar la siguiente priorización'),
            ],
        ];

        $version = $avatar->conversationVersions()->firstOrCreate(
            ['label' => 'Demo Gobierno Regional de Ica'],
            ['tree' => $tree],
        );

        $version->update(['tree' => $tree, 'status' => 'draft', 'published_at' => null]);
        $lines = app(ConversationTree::class)->lines($tree);
        $version->audioAssets()->whereNotIn('asset_key', array_keys($lines))->delete();

        foreach ($lines as $key => $text) {
            $version->audioAssets()->updateOrCreate(
                ['asset_key' => $key],
                [
                    'text' => $text,
                    'path' => null,
                    'duration_ms' => null,
                    'visemes' => null,
                    'status' => 'pending',
                    'error' => null,
                ],
            );
        }
    }

    /**
     * @param  list<string>  $keywords
     * @return array<string, mixed>
     */
    private function topic(string $id, string $title, array $keywords, string $summary, string $detail, string $next): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'keywords' => $keywords,
            'summary' => ['variants' => $this->variants($title, $summary, 'eje principal')],
            'detail' => ['variants' => $this->variants($title, $detail, 'desarrollo')],
            'next' => ['variants' => $this->variants($title, $next, 'siguiente paso')],
        ];
    }

    /** @return list<string> */
    private function variants(string $title, string $message, string $moment): array
    {
        return [
            "En esta demostración ficticia, {$title} busca {$message}.",
            "Sobre {$title}, la propuesta demo plantea {$message}.",
            "La idea central de {$title} en este ejemplo es {$message}.",
            "Dentro de este {$moment}, {$title} propone {$message}.",
            "Esta versión demostrativa entiende que {$title} debe {$message}.",
            "Como parte de la propuesta ficticia, {$title} considera {$message}.",
        ];
    }
}

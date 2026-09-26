<?php

namespace Database\Seeders;

use App\Models\Avatar;
use App\Services\ConversationTree;
use Illuminate\Database\Seeder;

class AvatarDemoSeeder extends Seeder
{
    public function run(): void
    {
        $avatar = Avatar::firstOrCreate(
            ['slug' => 'ica-demo'],
            [
                'name' => 'Anita Ica Demo',
                'public_title' => 'Gobierno Regional de Ica · Demo',
                'voice_profile' => 'anita',
                'rive_path' => 'assets/avatar/anita.riv',
                'status' => 'draft',
            ],
        );

        $avatar->fill([
            'name' => 'Anita Ica Demo',
            'public_title' => 'Gobierno Regional de Ica · Demo',
            'voice_profile' => 'anita',
            'rive_path' => 'assets/avatar/anita.riv',
        ]);
        if (! $avatar->publishedConversation()) {
            $avatar->status = 'draft';
        }
        $avatar->save();

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
            'social' => $this->social(),
            'topics' => [
                $this->topic('agua', 'Agua y saneamiento', ['agua', 'saneamiento', 'desague', 'riego'], 'priorizar agua segura, mantenimiento preventivo y seguimiento público de cada obra', 'crear un mapa de brechas, coordinar con municipalidades y publicar metas trimestrales', 'abrir mesas vecinales para revisar avances, alertas y prioridades de inversión'),
                $this->topic('seguridad', 'Seguridad ciudadana', ['seguridad', 'delincuencia', 'serenazgo', 'policia'], 'trabajar prevención, coordinación territorial y datos abiertos sobre incidencias', 'identificar puntos críticos, mejorar la iluminación y realizar campañas con organizaciones vecinales', 'convocar seguimientos por provincia y ajustar las acciones según resultados observables'),
                $this->topic('empleo', 'Empleo y emprendimiento', ['empleo', 'trabajo', 'emprendimiento', 'negocio', 'jovenes'], 'conectar capacitación, empleabilidad y pequeños emprendimientos con oportunidades regionales', 'impulsar alianzas con institutos, empresas y municipios, además de ferias transparentes de oportunidades', 'medir inserción laboral, continuidad de negocios y publicar resultados por provincia'),
                $this->topic('vias', 'Vías y conectividad', ['vias', 'carretera', 'camino', 'transporte', 'conectividad'], 'priorizar mantenimiento vial con criterios públicos de seguridad, conectividad y acceso a servicios', 'mantener un inventario de puntos vulnerables, cronogramas visibles y coordinación con provincias', 'evaluar avances físicos y presupuestales con supervisión ciudadana sobre plazos y calidad'),
                $this->topic('presupuesto', 'Presupuesto y participación', ['presupuesto', 'dinero', 'inversion', 'participacion', 'transparencia'], 'explicar el presupuesto regional en lenguaje sencillo y abrir espacios de priorización ciudadana', 'usar tableros públicos, audiencias por provincia y un calendario visible de inversiones', 'incorporar reportes ciudadanos y evaluaciones públicas anuales para mejorar la siguiente priorización'),
            ],
        ];

        $version = $avatar->conversationVersions()->firstOrCreate(
            ['label' => 'Demo Gobierno Regional de Ica · Conversación amistosa'],
            ['tree' => $tree, 'status' => 'draft'],
        );

        if ($version->status !== 'draft') {
            return;
        }

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
            'description' => "Explicación demostrativa, sencilla y cercana sobre {$title} y sus prioridades regionales.",
            'examples' => [
                "Quiero conocer las propuestas sobre {$title}.",
                "¿Qué plantea la demostración para {$keywords[0]}?",
                "Me gustaría entender mejor el tema de {$title}.",
            ],
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
            "Claro. En esta demostración ficticia, {$title} busca {$message}.",
            "Con gusto te cuento. Sobre {$title}, la propuesta demo plantea {$message}.",
            "La idea principal de {$title} en este ejemplo es {$message}.",
            "Si te parece, revisemos este {$moment}: {$title} propone {$message}.",
            "De manera sencilla, esta versión demostrativa plantea que {$title} debe {$message}.",
            "Un punto importante de la propuesta ficticia es que {$title} considera {$message}.",
        ];
    }

    /** @return array<string, array{description: string, examples: list<string>, keywords: list<string>, variants: list<string>}> */
    private function social(): array
    {
        return [
            'greeting' => $this->socialIntent(
                'Inicio amistoso de una conversación y presentación de Anita.',
                ['Hola', 'Buenos días, Anita', 'Hola, quisiera hacer una consulta'],
                ['hola', 'buenas', 'buen dia', 'buenos dias', 'buenas tardes', 'buenas noches', 'que tal'],
                [
                    'Hola, qué gusto conversar contigo. Soy Anita y estoy aquí para acompañarte en esta demostración de Ica.',
                    '¡Hola! Soy Anita. Gracias por acercarte; podemos conversar con calma sobre los temas de esta demo.',
                    'Bienvenida o bienvenido. Soy Anita y me alegra que estés aquí. ¿Qué tema te gustaría revisar primero?',
                    'Hola, encantada de escucharte. Puedo orientarte en los temas que tenemos preparados para esta demostración.',
                    'Qué gusto saludarte. Soy Anita y estoy lista para ayudarte a recorrer esta propuesta ficticia paso a paso.',
                    'Hola. Gracias por iniciar esta conversación conmigo; dime qué inquietud te gustaría conocer.',
                ],
            ),
            'gratitude' => $this->socialIntent(
                'Respuesta cálida a un agradecimiento sin cerrar necesariamente la conversación.',
                ['Gracias', 'Muchas gracias, Anita', 'Te agradezco la explicación'],
                ['gracias', 'te agradezco', 'muy amable'],
                [
                    'Con mucho gusto. Me alegra haberte acompañado; si quieres, podemos seguir conversando.',
                    'Gracias a ti por escuchar. Aquí estaré si te interesa revisar otro punto.',
                    'No hay de qué, ha sido un gusto ayudarte. Podemos continuar cuando quieras.',
                    'Me alegra que te haya servido. Dime si quieres profundizar o cambiar de tema.',
                    'Con gusto. Tu pregunta es importante y podemos revisar otra inquietud si la tienes.',
                    'Para eso estoy. Gracias por conversar conmigo y cuenta conmigo para seguir explorando la demo.',
                ],
            ),
            'acknowledgement' => $this->socialIntent(
                'Respuesta breve y amable a confirmaciones o entendimiento.',
                ['Está bien', 'Entiendo', 'Perfecto, gracias'],
                ['esta bien', 'de acuerdo', 'entiendo', 'perfecto', 'ok', 'listo'],
                [
                    'Perfecto, me alegra que quede claro. Cuando quieras, seguimos con otro tema.',
                    'Muy bien. Estoy lista para continuar contigo cuando lo necesites.',
                    'Qué bueno. Podemos profundizar en este punto o pasar a otro de la demostración.',
                    'De acuerdo. Tómate tu tiempo; aquí estaré para la siguiente pregunta.',
                    'Excelente, gracias por decírmelo. ¿Te gustaría conocer algo más?',
                    'Me alegra que te resulte claro. Podemos seguir paso a paso, sin apuro.',
                ],
            ),
            'capabilities' => $this->socialIntent(
                'Explica de forma amistosa los temas que Anita puede abordar.',
                ['¿De qué temas podemos hablar?', '¿Qué me puedes explicar?', '¿Sobre qué puedo preguntar?'],
                ['de que temas', 'que temas', 'que puedo preguntar', 'que puedes explicar', 'de que puedo hablar', 'que me puedes contar'],
                [
                    'Podemos conversar sobre agua y saneamiento, seguridad, empleo, vías y presupuesto. Elige el que más te interese.',
                    'Tengo información demostrativa sobre cinco temas: agua, seguridad, empleo, vías y presupuesto. ¿Cuál revisamos?',
                    'Puedes preguntarme por servicios de agua, seguridad ciudadana, oportunidades de empleo, conectividad vial o presupuesto.',
                    'Si quieres, te cuento sobre agua, seguridad, empleo, vías o presupuesto. También podemos combinar más de un tema.',
                    'Estoy preparada para explicarte los ejes de agua, seguridad, empleo, vías y presupuesto, con ejemplos sencillos.',
                    'Podemos empezar por el tema que te preocupe más: agua, seguridad, empleo, vías o presupuesto.',
                ],
            ),
            'clarification' => $this->socialIntent(
                'Introduce una explicación con otras palabras del tema recién conversado.',
                ['No entiendo', '¿Me lo explicas de otra forma?', 'No me quedó claro'],
                ['no entiendo', 'no comprendo', 'no me quedo claro', 'explicalo de otra forma', 'explica de otra forma', 'puedes repetir'],
                [
                    'Claro, no te preocupes. Te lo explico nuevamente con palabras más sencillas.',
                    'Por supuesto. Voy a decir la misma idea de otra manera para que sea más clara.',
                    'Gracias por decírmelo. Regresemos a ese punto y lo vemos con calma.',
                    'Claro que sí. Te lo repito de una forma más directa y fácil de seguir.',
                    'No hay problema; voy a reformular la explicación sin añadir información nueva.',
                    'Entiendo. Revisemos nuevamente la idea principal, paso a paso.',
                ],
            ),
            'change-topic' => $this->socialIntent(
                'Invita a cambiar a otro tema disponible.',
                ['Quiero otro tema', 'Cambiemos de tema', 'Ahora quiero hablar de otra cosa'],
                ['otro tema', 'cambiemos de tema', 'cambiar de tema', 'otra cosa', 'ahora quiero hablar'],
                [
                    'Claro, cambiemos de tema. Dime si prefieres agua, seguridad, empleo, vías o presupuesto.',
                    'Por supuesto. Podemos pasar a otro eje de la demostración; ¿cuál te interesa ahora?',
                    'Perfecto, dejemos ese punto por ahora. Estoy lista para conversar sobre cualquiera de los otros temas.',
                    'Sin problema. Tú marcas el rumbo: agua, seguridad, empleo, vías o presupuesto.',
                    'Claro, exploremos una nueva inquietud. Puedes decirme el tema que prefieras.',
                    'Me parece bien cambiar de tema. ¿Qué te gustaría revisar ahora?',
                ],
            ),
            'small-talk' => $this->socialIntent(
                'Respuesta cordial a una pregunta social breve.',
                ['¿Cómo estás?', '¿Qué tal te va?', '¿Cómo te va la vida?'],
                ['como estas', 'como te va', 'que tal estas', 'como te va la vida'],
                [
                    'Estoy muy bien, gracias por preguntar. Me alegra conversar contigo y estoy lista para ayudarte.',
                    'Muy bien, gracias. Estoy aquí para acompañarte en la consulta que tengas.',
                    'Todo va bien por aquí. Gracias por tu amabilidad; ¿sobre qué te gustaría conversar?',
                    'Estoy lista y contenta de ayudarte. Cuéntame qué tema tienes en mente.',
                    'Gracias por preguntar. Me encuentro lista para seguir esta conversación contigo.',
                    'Muy bien, y me alegra que conversemos. Cuando quieras, dime tu consulta.',
                ],
            ),
            'farewell' => $this->socialIntent(
                'Cierre amable cuando la persona se despide o indica que no necesita más ayuda.',
                ['No, gracias', 'Adiós', 'Hasta luego'],
                ['no gracias', 'adios', 'chau', 'hasta luego', 'nos vemos', 'eso es todo'],
                [
                    'De acuerdo, gracias por conversar conmigo. Que tengas un muy buen día.',
                    'Está bien, ha sido un gusto acompañarte. Cuando quieras, aquí estaré.',
                    'Gracias por tu tiempo. Me despido por ahora y te deseo que estés muy bien.',
                    'Perfecto, cerramos por aquí. Fue un gusto conversar contigo.',
                    'No hay problema. Gracias por visitarnos y hasta una próxima oportunidad.',
                    'Entendido. Me alegra haber podido ayudarte; que tengas un excelente día.',
                ],
            ),
        ];
    }

    /**
     * @param  list<string>  $examples
     * @param  list<string>  $keywords
     * @param  list<string>  $variants
     * @return array{description: string, examples: list<string>, keywords: list<string>, variants: list<string>}
     */
    private function socialIntent(string $description, array $examples, array $keywords, array $variants): array
    {
        return compact('description', 'examples', 'keywords', 'variants');
    }
}

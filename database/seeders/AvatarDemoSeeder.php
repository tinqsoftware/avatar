<?php

namespace Database\Seeders;

use App\Models\AudioAsset;
use App\Models\Avatar;
use App\Services\ConversationTree;
use Illuminate\Database\Seeder;

class AvatarDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $avatar = Avatar::updateOrCreate(
            ['slug' => 'ica-demo'],
            [
                'name' => 'Anita Ica Demo',
                'public_title' => 'Gobierno Regional de Ica · Demo',
                'voice_profile' => 'anita',
                'rive_path' => 'assets/avatar/anita.riv',
                'status' => 'published',
            ],
        );

        $tree = [
            'greeting' => ['variants' => [
                'Hola, soy Anita. Esta es una demostración ficticia de propuestas para el Gobierno Regional de Ica. ¿Sobre qué tema te gustaría conversar?',
                'Bienvenida o bienvenido. Soy Anita y te acompañaré en esta demostración ficticia para Ica. Puedes preguntarme por seguridad, agua, empleo, vías o presupuesto.',
            ]],
            'fallback' => ['variants' => [
                'Puedo orientarte sobre seguridad, agua, empleo, vías, presupuesto y participación ciudadana dentro de esta demostración ficticia.',
                'No encontré ese tema en esta propuesta demostrativa. Si quieres, pregúntame por agua, empleo, seguridad, vías o presupuesto.',
            ]],
            'connectors' => ['Claro, déjame revisar esa propuesta.', 'Entiendo. Un momento, por favor.', 'Estoy organizando esa información para ti.'],
            'topics' => [
                ['id' => 'agua', 'title' => 'Agua y saneamiento', 'keywords' => ['agua', 'saneamiento', 'desague', 'riego'], 'summary' => ['variants' => ['La propuesta ficticia plantea priorizar proyectos de agua segura y mantenimiento preventivo, con seguimiento público de cada obra.', 'En esta demostración, el eje de agua busca mejorar continuidad del servicio y publicar avances claros para las familias.']], 'detail' => ['variants' => ['La segunda parte propone un mapa regional de brechas, coordinación con municipalidades y metas trimestrales que cualquier persona pueda revisar.', 'También contempla priorizar zonas con mayor riesgo sanitario y capacitar comités locales para vigilar el mantenimiento.']], 'next' => ['variants' => ['Como siguiente paso, la propuesta ficticia abriría mesas vecinales para revisar avances, alertas y prioridades de inversión.', 'La tercera parte contempla reportes simples de presupuesto, cronograma y responsables por proyecto.']]],
                ['id' => 'seguridad', 'title' => 'Seguridad ciudadana', 'keywords' => ['seguridad', 'delincuencia', 'serenazgo', 'policia'], 'summary' => ['variants' => ['La propuesta ficticia de seguridad prioriza prevención, coordinación territorial y datos abiertos sobre incidencias.', 'En esta demostración, seguridad significa trabajar con municipalidades, comunidad y autoridades para prevenir riesgos.']], 'detail' => ['variants' => ['La ampliación plantea mapas de puntos críticos, iluminación coordinada y campañas con jóvenes y organizaciones vecinales.', 'Otra medida sería publicar indicadores periódicos para que las decisiones se puedan evaluar con información verificable.']], 'next' => ['variants' => ['La tercera parte incluye reuniones de seguimiento por provincia y ajustes según los resultados observados.', 'Finalmente, el plan ficticio propone un canal ciudadano de alertas con trazabilidad de la atención.']]],
                ['id' => 'empleo', 'title' => 'Empleo y emprendimiento', 'keywords' => ['empleo', 'trabajo', 'emprendimiento', 'negocio', 'jovenes'], 'summary' => ['variants' => ['La propuesta ficticia busca conectar capacitación, empleabilidad y pequeños emprendimientos con oportunidades regionales.', 'Este eje plantea dar información clara sobre capacitación y acompañamiento para emprendimientos locales.']], 'detail' => ['variants' => ['La segunda parte propone alianzas con institutos, empresas y municipios para acercar formación a cada provincia.', 'También contempla ferias transparentes de oportunidades y orientación básica para formalización de negocios.']], 'next' => ['variants' => ['Como tercera etapa, se medirían inserción laboral y continuidad de los negocios para corregir las acciones.', 'El cierre del eje plantea publicar resultados por provincia y recoger sugerencias de los participantes.']]],
                ['id' => 'vias', 'title' => 'Vías y conectividad', 'keywords' => ['vias', 'carretera', 'camino', 'transporte', 'conectividad'], 'summary' => ['variants' => ['La propuesta ficticia prioriza mantenimiento vial con criterios públicos de seguridad, conectividad y acceso a servicios.', 'En esta demostración, vías significa ordenar prioridades con evidencia y comunicar el estado de cada intervención.']], 'detail' => ['variants' => ['La ampliación incluye inventario de puntos vulnerables, cronogramas visibles y coordinación con las provincias.', 'Otra medida es incluir alertas por temporada y canales para reportar daños de forma ordenada.']], 'next' => ['variants' => ['La tercera parte propone una evaluación trimestral de avances físicos y presupuestales.', 'Para cerrar, la propuesta ficticia incorpora supervisión ciudadana sobre plazos y calidad.']]],
                ['id' => 'presupuesto', 'title' => 'Presupuesto y participación', 'keywords' => ['presupuesto', 'dinero', 'inversion', 'participacion', 'transparencia'], 'summary' => ['variants' => ['La propuesta ficticia plantea explicar el presupuesto regional en lenguaje sencillo y abrir espacios de priorización ciudadana.', 'Este eje busca que las personas conozcan qué se financia, por qué y cómo pueden hacer seguimiento.']], 'detail' => ['variants' => ['La segunda parte contempla tableros públicos, audiencias por provincia y un calendario visible de inversiones.', 'También propone publicar cambios presupuestales con una explicación breve y accesible.']], 'next' => ['variants' => ['Como siguiente parte, se incorporarían reportes ciudadanos sobre ejecución y calidad de los proyectos.', 'El cierre plantea evaluaciones públicas anuales para aprender y mejorar la siguiente priorización.']]],
            ],
        ];

        $version = $avatar->conversationVersions()->firstOrCreate(
            ['label' => 'Demo Gobierno Regional de Ica'],
            ['tree' => $tree, 'status' => 'published', 'published_at' => now()],
        );

        foreach (app(ConversationTree::class)->lines($tree) as $key => $text) {
            AudioAsset::firstOrCreate(['conversation_version_id' => $version->id, 'asset_key' => $key], ['text' => $text]);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Unit;
use App\Models\Owner;
use App\Models\Resident;
use App\Enums\ResidentRelation;

class OwnersSeeder extends Seeder
{
    public function run()
    {
        $neighborhoodId = session('neighborhood_id') ?? 1;

        $ownersData = [
            ['uf' => '1', 'name' => 'Formica German', 'email' => 'Germanjformica@gmail.com', 'residents' => [['n' => 'Formica German', 'r' => 'Propietario'], ['n' => 'Vanina Formica', 'r' => 'Hermana']]],
            ['uf' => '2', 'name' => 'Graciela', 'email' => '', 'residents' => [['n' => 'Graciela', 'r' => 'Propietario']]],
            ['uf' => '3', 'name' => 'Jose Cannistraci', 'email' => 'Josecannistraci5456@hotmail.com', 'residents' => [['n' => 'Jose Cannistraci', 'r' => 'Dueño'], ['n' => 'Analia', 'r' => 'Pareja']]],
            ['uf' => '4', 'name' => 'Martin Villar', 'email' => 'martinvillar@yahoo.com', 'residents' => [['n' => 'Viviana Cecilia Rosta', 'r' => 'Pareja']]],
            ['uf' => '5', 'name' => 'Laura Estela Crupi', 'email' => 'lauracgambato@gmail.com', 'residents' => [['n' => 'Llanca Dihue Chirino Crupi', 'r' => 'Hija']]],
            ['uf' => '6', 'name' => 'Rodrigo Rigo', 'email' => 'manuela_alfonso@hotmail.com', 'residents' => [['n' => 'Manuela Alfosno', 'r' => 'Conyugue']]],
            ['uf' => '7', 'name' => 'Daniel Pautasso', 'email' => 'danielpautasso@hotmail.com', 'residents' => [['n' => 'Daniel Pautasso', 'r' => 'Yo']]],
            ['uf' => '8', 'name' => 'Rubén Genovese', 'email' => 'ruben.genovese@gmail.com', 'residents' => [['n' => 'María Virginia Gonzalez', 'r' => 'Esposa'], ['n' => 'Juan Carlos Gonzalez', 'r' => 'Suegro'], ['n' => 'María Fernanda Gonzalez', 'r' => 'Cuñada'], ['n' => 'Roberto Genovese', 'r' => 'Hermano'], ['n' => 'Cristina Rodriguez', 'r' => 'Cuñada']]],
            ['uf' => '9', 'name' => 'Gianfranco Tasteri', 'email' => 'g.tasteri@gmail.com', 'residents' => [['n' => 'Patricia Tasteri', 'r' => 'Madre'], ['n' => 'Alejandro Alpeggiani', 'r' => 'Padre']]],
            ['uf' => '10', 'name' => 'Laura Fernanda Velasco', 'email' => 'laura_velasco@hotmail.com', 'residents' => [['n' => 'Dora Raveaux', 'r' => 'Mamá'], ['n' => 'Leandro Nahim', 'r' => 'Pareja']]],
            ['uf' => '11', 'name' => 'Bríccola, Marcelo Javier', 'email' => 'marcebriccola@yahoo.com.ar', 'residents' => [['n' => 'QUIROGA, Daniel', 'r' => 'Cuñado'], ['n' => 'BRICCOLA, Paola', 'r' => 'Hermana'], ['n' => 'REINOSO, Claudio', 'r' => 'Esposo']]],
            ['uf' => '12', 'name' => 'Mariano Javier Caballero', 'email' => 'marianojcaballero@gmail.com', 'residents' => [['n' => 'Lorenzo Fernanda', 'r' => 'Esposa'], ['n' => 'Caballero Agustína', 'r' => 'Hija'], ['n' => 'Caballero Camila', 'r' => 'Hija']]],
            ['uf' => '13', 'name' => 'Mario Andres Giunta', 'email' => 'mariogiunta10.mag@gmail.com', 'residents' => [['n' => 'Maria Virginia Brain', 'r' => 'Esposa']]],
            ['uf' => '14', 'name' => 'Aldana Cecilia Rofrano', 'email' => 'aldanarofrano@gmail.com', 'residents' => [['n' => 'Aldana Rofrano', 'r' => 'Yo'], ['n' => 'Javier Lombardo', 'r' => 'Pareja']]],
            ['uf' => '15', 'name' => 'Jorge Ariel Zarate', 'email' => 'jorgearielzarate@yahoo.com.ar', 'residents' => [['n' => 'Ana Viviana Gonzalez', 'r' => 'Cónyuge'], ['n' => 'Gimena Zarate', 'r' => 'Hija'], ['n' => 'Jorge Zarate', 'r' => 'Propietario']]],
            ['uf' => '16', 'name' => 'Funes Ivan', 'email' => 'Ivanantoniofunes@gmail.com', 'residents' => [['n' => 'Ivan Funes', 'r' => 'Propietario'], ['n' => 'Fabian Funes', 'r' => 'Familiar']]],
            ['uf' => '17', 'name' => 'Andrea Mohammad', 'email' => 'andrea_mohammad@yahoo.com', 'residents' => [['n' => 'Juan Carlos Mohammad', 'r' => 'Padre']]],
            ['uf' => '18', 'name' => 'Santilli Gonzalo', 'email' => 'Cintiayguadi@hotmail.com.ar', 'residents' => [['n' => 'Cintia Arroyo', 'r' => 'Esposa'], ['n' => 'Guadalupe Santilli', 'r' => 'Hija']]],
            ['uf' => '19', 'name' => 'Sergio Cappelli', 'email' => 'gaby_sabita@hotmail.com', 'residents' => [['n' => 'Gabriela Gizzea', 'r' => 'Titular'], ['n' => 'Bianca Cappelli', 'r' => 'Hija']]],
            ['uf' => '21', 'name' => 'Tomás Burrieza', 'email' => 'tomyburrieza@gmail.com', 'residents' => [['n' => 'Tomás Burrieza', 'r' => 'Propietario'], ['n' => 'Adolfo Daniel Burrieza', 'r' => 'Padre']]],
            ['uf' => '22', 'name' => 'Rocio Virhuez', 'email' => 'rovirhuez@gmail.com', 'residents' => [['n' => 'Rocio Virhuez', 'r' => 'Titular']]],
            ['uf' => '23', 'name' => 'Laura Castro', 'email' => 'laucast2002@yahoo.com.ar', 'residents' => [['n' => 'Fernando Moll', 'r' => 'Esposo']]],
            ['uf' => '24', 'name' => 'Mario Gudiño', 'email' => 'magaligudino@gmail.com', 'residents' => [['n' => 'Liliana Avezou', 'r' => 'Esposa'], ['n' => 'Magali Gudiño', 'r' => 'Hija']]],
            ['uf' => '25', 'name' => 'Analia Rojas', 'email' => 'analiarojas18@yahoo.com.ar', 'residents' => [['n' => 'Antonella Velasco', 'r' => 'Hija'], ['n' => 'Gustavo Chavez', 'r' => 'Pareja']]],
            ['uf' => '26', 'name' => 'Claudia Gutierrez', 'email' => 'm.claudiagutierrez71@gmail.com', 'residents' => [['n' => 'Claudia Gutierrez', 'r' => 'Titular'], ['n' => 'Rodolfo Pravata', 'r' => 'Marido']]],
            ['uf' => '27', 'name' => 'Javier Coria', 'email' => 'paularociofs@gmail.com', 'residents' => [['n' => 'Paula Fisigaro', 'r' => 'Esposa'], ['n' => 'Coria Javier', 'r' => 'Titular']]],
            ['uf' => '28', 'name' => 'Juan Pablo Villarruel', 'email' => 'juanpablovillarruel21@gmail.com', 'residents' => [['n' => 'Emilio Villarruel', 'r' => 'Hermano']]],
        ];

        foreach ($ownersData as $data) {
            $unit = Unit::updateOrCreate(
                ['neighborhood_id' => $neighborhoodId, 'uf_number' => $data['uf']]
            );

            $owner = Owner::updateOrCreate(
                ['unit_id' => $unit->id],
                ['full_name' => $data['name'], 'email' => $data['email']]
            );

            foreach ($data['residents'] as $res) {
                Resident::create([
                    'owner_id' => $owner->id,
                    'full_name' => $res['n'],
                    'relation' => $this->mapRelation($res['r']),
                ]);
            }
        }
    }

    private function mapRelation(string $relation): string
    {
        $r = strtolower($relation);

        return match (true) {
            // Owner
            str_contains($r, 'propietario'),
            str_contains($r, 'dueño'),
            str_contains($r, 'yo'),
            str_contains($r, 'titular') => ResidentRelation::OWNER->value,

            // Spouse / Partner
            str_contains($r, 'esposa'),
            str_contains($r, 'conyugue'),
            str_contains($r, 'pareja'),
            str_contains($r, 'esposo'),
            str_contains($r, 'marido'),
            str_contains($r, 'marinovio') => ResidentRelation::SPOUSE->value,

            // Daughter
            str_contains($r, 'hija') => ResidentRelation::DAUGHTER->value,

            // Son
            str_contains($r, 'hijo') => ResidentRelation::SON->value,

            // Todo lo demás (Hermano, Suegro, Padre, etc. que no están en tu Enum)
            default => ResidentRelation::OTHER->value,
        };
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Faker\Factory as Faker;

class NamaWargaTerdaftarSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('id_ID');

        $wargaList = [
            ['nama' => 'ALEKSANDER SUMBALUWU', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1964-10-10'],
            ['nama' => 'ALAM SARAGIH', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1963-04-18'],
            ['nama' => 'DONY TJEN', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1960-05-05'],
            ['nama' => 'LAURENTIUS PRAMBODO', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1955-01-01'],
            ['nama' => 'SUHARMINTO', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1943-02-12'],
            ['nama' => 'VITA THERESIA', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1951-10-14'],
            ['nama' => 'EKO SUJATMIKO', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1961-01-16'],
            ['nama' => 'TRIANA EKO', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1964-01-25'],
            ['nama' => 'HERU PRIYAMBODO', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1962-03-21'],
            ['nama' => 'DEWI MASITOH', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1963-01-05'],
            ['nama' => 'BAMBANG SUPARDI', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1953-07-27'],
            ['nama' => 'TOETIK ARMIATI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1956-07-05'],
            ['nama' => 'HILWAN', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1958-08-14'],
            ['nama' => 'IRWATI SUTOPO', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1957-12-22'],
            ['nama' => 'WONG WIE HKIM', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1955-11-08'],
            ['nama' => 'SRI REJEKI HANDAYANI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1958-04-14'],
            ['nama' => 'WARSITO SISWOWIYOTO', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => null],
            ['nama' => 'ABDJAD RETNO IRIANIE', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => null],
            ['nama' => 'MARDIANA ALIT SANTIKA', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1957-03-11'],
            ['nama' => 'LELA LESTARI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => null],
            ['nama' => 'REFNIDA ZUBIR', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => null],
            ['nama' => 'ELLY RAMANTY NUSATIMAH', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1960-07-05'],
            ['nama' => 'ERNI TAUFIK', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1952-05-09'],
            ['nama' => 'ALITO', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1965-09-20'],
            ['nama' => 'RINA SAHAN', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1963-07-15'],
            ['nama' => 'AGUS RAMADI', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1963-12-12'],
            ['nama' => 'NANA ROSDIANA', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1957-04-16'],
            ['nama' => 'YAMI MIRYANTI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => null],
            ['nama' => 'H PENA SUPENA', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => null],
            ['nama' => 'ERATOWISMA', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1965-10-01'],
            ['nama' => 'FITYATUN MALIKHAN', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => null],
            ['nama' => 'YAMIN HALIM', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1947-04-15'],
            ['nama' => 'DANANG J.P SEMBADO,S.E', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1961-04-13'],
            ['nama' => 'SETYANI INDRASTUTI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1960-07-24'],
            ['nama' => 'H.MARWIN UMAR', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => null],
            ['nama' => 'EKA SUGIARTOMO', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1966-02-20'],
            ['nama' => 'ERYANA ROBITOH', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => null],
            ['nama' => 'RIZKA MUTIA', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1973-03-26'],
            ['nama' => 'NUR JANNAH', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => null],
            ['nama' => 'IMAM SYAHYUDI', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1974-04-14'],
            ['nama' => 'LYDIA MARLINA MENSUA', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1978-03-05'],
            ['nama' => 'MEIDA SITUMEANG', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1987-05-27'],
            ['nama' => 'BILLY ZUSMAILY', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1972-08-24'],
            ['nama' => 'MARINI RASYID', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1976-07-31'],
            ['nama' => 'NURAINI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1970-05-21'],
            ['nama' => 'M ARIEF WIRYA', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1996-01-21'],
            ['nama' => 'RUBAIKAH', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1995-10-05'],
            ['nama' => 'BADIAH', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1951-03-30'],
            ['nama' => 'LWAN', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => null],
            ['nama' => 'WIYANA', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => null],
            ['nama' => 'HERU WARDONO', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1962-05-31'],
            ['nama' => 'HARTINI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1958-09-05'],
            ['nama' => 'ROESDIMANTINI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1959-04-26'],
            ['nama' => 'ROSSI ARSILIA W', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1965-04-12'],
            ['nama' => 'GUSNITA HELMI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => null],
            ['nama' => 'ABU BAKAR MAJID', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1947-04-21'],
            ['nama' => 'PALUPI WIDJAYANTI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1961-05-10'],
            ['nama' => 'RINI AMINATUN', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1987-12-09'],
            ['nama' => 'FITRIANA', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1994-03-13'],
            ['nama' => 'NADYA N', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1996-08-17'],
            ['nama' => 'PRIYO ISWANTO', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1962-05-10'],
            ['nama' => 'DARWITO', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1951-02-14'],
            ['nama' => 'AI HAYATI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1986-01-27'],
            ['nama' => 'RUDIYANTO', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1961-03-14'],
            ['nama' => 'IRNAH', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1979-08-06'],
            ['nama' => 'ALIT SANTHIKA', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => null],
            ['nama' => 'DENISE', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1993-12-09'],
            ['nama' => 'RIYAN', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1993-05-25'],
            ['nama' => 'ASTUTI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1972-07-27'],
            ['nama' => 'ENDAH FERDIKAWATI', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1995-05-21'],
            ['nama' => 'JAMILAH', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1962-09-14'],
            ['nama' => 'MURSIAH', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1973-08-04'],
            ['nama' => 'IMAM NURJANTO', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1943-03-06'],
            ['nama' => 'SYAFRUL', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '1958-07-20'],
            ['nama' => 'NUNUNG SARININGSIH', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1968-02-09'],
            ['nama' => 'NELWATI DJAAFAR', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => null],
        ];

        $dusunList = ['Krajan', 'Cibuntu', 'Cikadu', 'Sukaasih', 'Cipadu'];
        $rtList = ['001', '002', '003', '004', '005'];
        $rwList = ['008', '009', '010'];

        foreach ($wargaList as $index => $data) {
            $nama = $data['nama'];
            $jenisKelamin = $data['jenis_kelamin'];

            // Tanggal lahir
            if ($data['tanggal_lahir']) {
                $tglLahir = Carbon::createFromFormat('Y-m-d', $data['tanggal_lahir']);
            } else {
                // Jika tidak ada tanggal lahir, buat dummy usia 40-85 tahun
                $tglLahir = Carbon::now()->subYears(rand(40, 85))->subDays(rand(0, 365));
            }

            // NIK unik (kode wilayah Tangerang + Larangan + random + urutan)
            $nik = '3275' . // Kota Tangerang
                   '14' .  // Kec. Larangan
                   '01' .
                   str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT) .
                   str_pad($index + 1, 4, '0', STR_PAD_LEFT);

            DB::table('warga')->insert([
                'nama' => $nama,
                'nik' => substr($nik, 0, 16),
                'tanggal_lahir' => $tglLahir->format('Y-m-d'),
                'jenis_kelamin' => $jenisKelamin,
                'alamat' => $faker->streetAddress,
                'no_hp' => $faker->numerify('08##########'),
                'status_nikah' => $faker->randomElement(['Menikah', 'Tidak Menikah']),
                'pekerjaan' => $faker->jobTitle,
                'dusun' => $faker->randomElement($dusunList),
                'rt' => $faker->randomElement($rtList),
                'rw' => $faker->randomElement($rwList),
                'desa' => 'CIPADU JAYA',
                'kecamatan' => 'LARANGAN',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("Warga {$nama} berhasil dibuat.");
        }

        $this->command->info('SELESAI! ' . count($wargaList) . ' data warga telah berhasil disimpan.');
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Faker\Factory as Faker;

class WargaDenganRiwayatPemeriksaanSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('id_ID');

        $namaList = [
            'SITI NURHALIZA', 'AHMAD SUBARDI', 'TJAHJONO SUTRISNO', 'DEWI SARTIKA',
            'BUDI SANTOSO', 'SRI WIDYASTUTI', 'MAMAN SUPARMAN', 'NENENG HASANAH',
            'SUPRIYADI', 'SUTARMI'
        ];

        $dusunList = ['Krajan', 'Cibuntu', 'Cikadu', 'Sukaasih', 'Cipadu'];
        $rtList = ['001', '002', '003', '004', '005'];
        $rwList = ['008', '009', '010'];

        foreach ($namaList as $i => $nama) {
            $tglLahir = Carbon::now()->subYears(rand(45, 85))->subDays(rand(0, 365));
            $jenisKelamin = in_array($i, [0,3,5,7]) ? 'Perempuan' : 'Laki-laki';

            $nik = '3275' . // Kode Tangerang
                   '14' . // Larangan
                   '01' . // Tanggal lahir dummy
                   str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT) .
                   str_pad($i + 1, 2, '0', STR_PAD_LEFT);

            $wargaId = DB::table('warga')->insertGetId([
                'nama'           => $nama,
                'nik'            => substr($nik, 0, 16),
                'tanggal_lahir'  => $tglLahir->format('Y-m-d'),
                'jenis_kelamin'  => $jenisKelamin,
                'alamat'         => $faker->streetAddress,
                'no_hp'          => $faker->numerify('08##########'), // 12 digit, mulai 08 → hasil: 081234567890
                'status_nikah'   => $faker->randomElement(['Menikah', 'Tidak Menikah']),
                'pekerjaan'      => $faker->jobTitle,
                'dusun'          => $faker->randomElement($dusunList),
                'rt'             => $faker->randomElement($rtList),
                'rw'             => $faker->randomElement($rwList),
                'desa'           => 'CIPADU JAYA',
                'kecamatan'      => 'LARANGAN',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // 6 sampai 15 kali periksa
            $jumlahPeriksa = rand(6, 15);

            for ($j = 0; $j < $jumlahPeriksa; $j++) {
                $tanggalPeriksa = Carbon::now()
                    ->subMonths(rand(0, 36))
                    ->subDays(rand(0, 28))
                    ->startOfMonth() // biar rapi bulanan
                    ->addDays(rand(1, 25));

                $beratBadan  = rand(400, 1200) / 10; // 40.0 – 120.0 kg
                $tinggiBadan = rand(1450, 1850) / 10; // 145.0 – 185.0 cm

                $sistole  = rand(90, 190);
                $diastole = rand(50, 120);
                $gulaDarah = rand(70, 350);

                $usia = $tglLahir->diffInYears($tanggalPeriksa);

                // PUMA 8 pertanyaan → acak Ya/Tidak
                $puma = [
                    'puma_napas_pendek' => $faker->boolean(25) ? '1' : '0',
                    'puma_dahak'        => $faker->boolean(20) ? '1' : '0',
                    'puma_batuk'        => $faker->boolean(25) ? '1' : '0',
                    'puma_spirometri'   => $faker->boolean(12) ? '1' : '0',
                ];

                // TBC
                $tbc = ['Ya', 'Tidak'];
                $tbc_batuk     = $faker->randomElement($tbc);
                $tbc_demam     = $faker->randomElement($tbc);
                $tbc_bb_turun  = $faker->randomElement($tbc);
                $tbc_kontak    = $faker->randomElement($tbc);

                // Kontrasepsi hanya untuk perempuan
                $kontrasepsi = $jenisKelamin === 'Perempuan'
                    ? $faker->randomElement(['Ya', 'Tidak'])
                    : 'Tidak';

                DB::table('pemeriksaan_dewasa_lansia')->insert([
                    'warga_id'             => $wargaId,
                    'tanggal_periksa'      => $tanggalPeriksa->format('Y-m-d'),

                    'berat_badan'          => $beratBadan,
                    'tinggi_badan'         => $tinggiBadan,

                    'lingkar_perut'        => rand(65, 100),
                    'lingkar_lengan_atas'  => rand(20, 40),

                    'sistole'              => $sistole,
                    'diastole'             => $diastole,
                    'gula_darah'           => $gulaDarah,

                    'mata_kanan'           => $faker->randomElement(['N', 'G', null]),
                    'mata_kiri'            => $faker->randomElement(['N', 'G', null]),
                    'telinga_kanan'        => $faker->randomElement(['N', 'G', null]),
                    'telinga_kiri'         => $faker->randomElement(['N', 'G', null]),

                    'merokok'              => $faker->randomElement(['0', '1', '2']),

                    // PUMA 8 pertanyaan
                    'puma_napas_pendek'    => $puma['puma_napas_pendek'],
                    'puma_dahak'           => $puma['puma_dahak'],
                    'puma_batuk'           => $puma['puma_batuk'],
                    'puma_spirometri'      => $puma['puma_spirometri'],
                    // skor_puma & puma_rujuk otomatis dari stored column

                    // TBC
                    'tbc_batuk'            => $tbc_batuk,
                    'tbc_demam'            => $tbc_demam,
                    'tbc_bb_turun'         => $tbc_bb_turun,
                    'tbc_kontak'           => $tbc_kontak,
                    // tbc_rujuk otomatis

                    'usia'                 => $usia,
                    'wawancara_kontrasepsi'=> $kontrasepsi,
                    'jenis_kontrasepsi'    => $kontrasepsi === 'Ya' ? $faker->randomElement(['Pil', 'Suntik', 'IUD', 'Implan', null]) : null,
                    'edukasi'              => $faker->paragraph(2),
                    'rujuk_puskesmas'     => false, // nanti diisi manual atau dari logic jika perlu
                    'catatan'              => $faker->optional(0.4)->sentence(),

                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            $this->command->info("{$nama} → {$jumlahPeriksa} kali periksa berhasil dibuat.");
        }

        $this->command->info('SELESAI! 10 warga dengan total ±100 riwayat pemeriksaan dewasa/lansia telah dibuat.');
    }
}
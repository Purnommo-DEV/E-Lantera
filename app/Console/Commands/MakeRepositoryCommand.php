<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeRepositoryCommand extends Command
{
    /**
     * Nama perintah artisan yang akan dipanggil.
     *
     * Contoh: php artisan make:repository Masjid
     */
    protected $signature = 'make:repository {name : Nama repository tanpa kata "Repository"} {--module= : (Opsional) Subfolder/module repository}';

    /**
     * Deskripsi singkat perintah.
     */
    protected $description = 'Membuat Interface dan Repository sekaligus (otomatis)';

    /**
     * Jalankan perintah artisan.
     */
    public function handle()
    {
        $name = ucfirst($this->argument('name')); // Nama repository (ex: Masjid)
        $module = $this->option('module'); // Contoh: --module=mrj

        // Tentukan path folder
        $interfaceDir = app_path('Interfaces');
        $repositoryDir = app_path('Repositories' . ($module ? "/{$module}" : ''));

        // Tentukan nama file
        $interfacePath = "{$interfaceDir}/{$name}RepositoryInterface.php";
        $repositoryPath = "{$repositoryDir}/{$name}Repository.php";

        // Cegah overwrite
        if (File::exists($interfacePath) || File::exists($repositoryPath)) {
            $this->error('❌ Repository atau Interface sudah ada!');
            return;
        }

        // Pastikan folder ada
        File::ensureDirectoryExists($interfaceDir);
        File::ensureDirectoryExists($repositoryDir);

        // === Buat Interface ===
        $interfaceContent = "<?php

        namespace App\Interfaces;

        interface {$name}RepositoryInterface
        {
            //
        }
        ";
        File::put($interfacePath, $interfaceContent);

        // === Buat Repository ===
        $repositoryNamespace = "App\\Repositories" . ($module ? "\\{$module}" : '');
        $repositoryContent = "<?php

        namespace {$repositoryNamespace};

        use App\Interfaces\\{$name}RepositoryInterface;

        class {$name}Repository implements {$name}RepositoryInterface
        {
            //
        }
        ";
        File::put($repositoryPath, $repositoryContent);

        // === Output sukses ===
        $this->info("✅ Berhasil membuat:");
        $this->line("- {$interfacePath}");
        $this->line("- {$repositoryPath}");
    }
}

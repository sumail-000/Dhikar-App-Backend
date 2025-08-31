now <?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'auto_assign_enabled')) {
                $table->boolean('auto_assign_enabled')->default(false)->after('is_public');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'auto_assign_enabled')) {
                $table->dropColumn('auto_assign_enabled');
            }
        });
    }
};
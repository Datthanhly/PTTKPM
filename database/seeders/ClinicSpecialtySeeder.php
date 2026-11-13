<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClinicSpecialtySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Keep existing IDs, doctor assignments and administrator changes.
            if (! Specialty::where('name', 'Nội khoa')->exists()) {
                Specialty::where('code', 'NOI')->where('name', 'Nội tổng quát')->update(['name' => 'Nội khoa']);
            }

            foreach ([
                ['NOI', 'Nội khoa', 'Khám và tư vấn các vấn đề sức khỏe nội khoa.'],
                ['NGOAI', 'Ngoại khoa', 'Khám, tư vấn và theo dõi các bệnh lý ngoại khoa.'],
                ['SAN', 'Sản phụ khoa', 'Chăm sóc sức khỏe phụ nữ và tư vấn sản phụ khoa.'],
                ['NHI', 'Nhi khoa', 'Chăm sóc sức khỏe trẻ em.'],
                ['TMH', 'Tai mũi họng', 'Khám và tư vấn các bệnh lý tai, mũi, họng.'],
                ['RHM', 'Răng hàm mặt', 'Khám và tư vấn chăm sóc sức khỏe răng miệng.'],
                ['DALIEU', 'Da liễu', 'Khám và tư vấn các vấn đề về da.'],
                ['MAT', 'Mắt', 'Khám và tư vấn chăm sóc sức khỏe mắt.'],
                ['TAMTHAN', 'Sức khỏe tâm thần', 'Tư vấn và hỗ trợ chăm sóc sức khỏe tâm thần.'],
                ['TAMLY', 'Tâm lý', 'Lắng nghe, thấu hiểu và tư vấn tâm lý.'],
            ] as [$code, $name, $description]) {
                if (! Specialty::where('code', $code)->exists()) {
                    Specialty::firstOrCreate(['name' => $name], compact('code', 'description'));
                }
            }
        });
    }
}

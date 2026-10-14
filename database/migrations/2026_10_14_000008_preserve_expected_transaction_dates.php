<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        Schema::table('transactions',fn(Blueprint $t)=>$t->date('expected_date')->nullable());
        DB::table('transactions')->whereIn('status',['planned','cancelled'])->update(['expected_date'=>DB::raw('date')]);
    }
    public function down(): void {Schema::table('transactions',fn(Blueprint $t)=>$t->dropColumn('expected_date'));}
};

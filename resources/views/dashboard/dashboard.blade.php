@extends('dashboard.layout')

@section('dashboard_content')
    @include('dashboard.dashboard_content', [
    'hospitalsCount' => $hospitalsCount,
    'retirementHomesCount' => $retirementHomesCount,
    'patientsCount' => $patientsCount,
    ])
<script>

    var hospital = {
        colors:['#10c469'],
        stroke: {
        curve: 'smooth',
        },
        chart: {
            type: 'area'
        },
        series: [{
            name: 'Hospitals',
            data: []
        }],
        xaxis: {
            categories: []
        }
    }
    var arrayHospital = @json($countHospital);
    for(i=0; i<=arrayHospital.length-1; i++){
        hospital.series[0].data.push(arrayHospital[i]['count']);
        hospital.xaxis.categories.push(arrayHospital[i]['month_name']);
    }


    var retirmentHome = {
        colors:['#0d6efd'],
        stroke: {
        curve: 'smooth',
        },
        chart: {
            type: 'area'
        },
        series: [{
            name: 'Retirement Home',
            data: []
        }],
        xaxis: {
            categories: []
        }
    }

    var arrayRetirementHome = @json($countRetirementHome);
    for(i=0; i<=arrayRetirementHome.length-1; i++){
        retirmentHome.series[0].data.push(arrayRetirementHome[i]['count']);
        retirmentHome.xaxis.categories.push(arrayRetirementHome[i]['month_name']);
    }

    var chart1 = new ApexCharts(document.querySelector("#hospitalchart"), hospital);
    var chart2 = new ApexCharts(document.querySelector("#retirementHomechart"), retirmentHome);
    chart1.render();
    chart2.render();
</script>    
@endsection

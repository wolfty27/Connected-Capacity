<script>
    function myMap() {
        var test= {lat: {{ $data['latitude'] }}, lng: {{ $data['longitude'] }}};
        var map = new google.maps.Map(document.getElementById('googleMap'), {
            zoom: 4,
            center: test
        });
        var marker = new google.maps.Marker({
            position: test,
            map: map
        });
    }
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_API_KEY') }}&callback=myMap"></script>

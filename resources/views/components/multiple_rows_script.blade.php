<script>
    var row = {{ isset($row) ? ($row+1) : 1 }};
    $(document).on("click", "#add-row", function () {
        var new_row =
            '<tr id="row' +
            row +
            '"><td><input name="tier[]" type="text" placeholder="Tier" class="form-control" required/></td>' +
            '<td><input name="retirement_home_price[]" type="price" placeholder="500" class="form-control" required/></td>' +
            '<td><input name="hospital_price[]" type="price" placeholder="700" class="form-control" required/></td>' +
            '<td><input class="delete-row btn btn-primary" type="button" value="Delete" /></td></tr>';

        $("#test-body").append(new_row);
        row++;
        return false;
    });

    // Remove criterion
    $(document).on("click", ".delete-row", function () {
//  alert("deleting row#"+row);
        if (row > 1) {
            $(this).closest("tr").remove();
            row--;
        }
        return false;
    });
</script>

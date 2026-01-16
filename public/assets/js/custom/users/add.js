$(document).ready(function () {
    $('.tab-link').on('click', function (e) {
        e.preventDefault();
        let targetId = $(this).data('target');

        $('.tab-link')
            .removeClass('text-[#FB9300] bg-orange-50 active-tab')
            .addClass('text-gray-500 hover:bg-gray-50 hover:text-[#FB9300]');

        $('.tab-link svg').removeClass('text-[#FB9300]').addClass('text-gray-300 group-hover:text-[#FB9300]');

        $(this)
            .removeClass('text-gray-500 hover:bg-gray-50 hover:text-[#FB9300]')
            .addClass('text-[#FB9300] bg-orange-50 active-tab');

        $(this).find('svg').removeClass('text-gray-300').addClass('text-[#FB9300]');

        $('.tab-content').addClass('hidden');
        $('#' + targetId).removeClass('hidden');
    });

    $('.select2').select2({
        width: '100%',
        allowClear: true
    });

    $('#company_id').on('change', function () {
        let companyId = $(this).val();
        let departementSelect = $('#departement_id');
        let routeTemplate = $(this).data('route-departments');

        if (!routeTemplate) {
            console.error('Route for departments not found');
            return;
        }

        departementSelect.html('<option>Loading...</option>').trigger('change');

        if (!companyId) {
            departementSelect.html('<option value="">Pilih Departement...</option>');
            return;
        }

        $.ajax({
            url: routeTemplate.replace(':id', companyId),
            type: 'GET',
            success: function (data) {
                departementSelect.empty().append('<option value="">Pilih Departement...</option>');
                $.each(data, function (i, dep) {
                    departementSelect.append(`<option value="${dep.id}">${dep.name}</option>`);
                });
                departementSelect.trigger('change');
            }
        });
    });
});

window.previewImage = function(event) {
    const reader = new FileReader();
    reader.onload = function () {
        document.getElementById('preview-image').src = reader.result;
    };
    reader.readAsDataURL(event.target.files[0]);
}

$(document).on('submit', '#registerUser', function (e) {
    e.preventDefault();
    var formData = new FormData(this);
    formData.append("save_users", true);

    $('.error-message').text('');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            var res = jQuery.parseJSON(response);

            $('#errorMessage').addClass('d-none').text('');
            $('#successMessage').addClass('d-none').text('');

            if (res.status == 422) {
                if (res.errors.username) {
                    $('#usernameError').text(res.errors.username);
                }
                if (res.errors.password) {
                    $('#passwordError').text(res.errors.password);
                }
                if (res.errors.role) {
                    $('#roleError').text(res.errors.role);
                }
            } else if (res.status == 200) {
                $('#successMessage').removeClass('d-none').text(res.message);
                $('#registerUser')[0].reset();

                setTimeout(function () {
                    location.reload();
                }, 2000);
            } else if (res.status == 500) {
                $('#errorMessage').removeClass('d-none').text(res.message);
            }
        }
    });
});

$(document).on('submit', '#saveCategories', function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    formData.append("save_categories", true);

    $('.error-message').text('');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            var res = jQuery.parseJSON(response);

            $('#errorMessage').addClass('d-none').text('');
            $('#successMessage').addClass('d-none').text('');

            if (res.status == 422) {
                $.each(res.errors, function(field, message) {
                    $('<div class="error-message text-danger">' + message + '</div>').insertAfter('[name="' + field + '"]');
                });
            } else if (res.status == 200) {
                $('#successMessage').removeClass('d-none').text(res.message);
                $('#addCategoryModal').modal('hide');
                $('#saveCategories')[0].reset();
                alertify.set('notifier','position', 'top-right');
                alertify.success('<i class="fa fa-check-circle"></i> ' + res.message);

                setTimeout(function () {
                    location.reload();
                }, 2000);
            } else if (res.status == 500) {
                alert(res.message);
            }
        }
    });
});

$(document).on('click', '.editCategoryBtn', function () {
    var category_id = $(this).val();
    
    $.ajax({
        type: "GET",
        url: "query.php?category_id=" + category_id,
        success: function (response) {
            var res = jQuery.parseJSON(response);
            if(res.status == 404) {
                alert(res.message);

            } else if(res.status == 200){
                $('#category_id').val(res.data.category_id);
                $('#category_name').val(res.data.category_name);      
                
                $('#editCategoryModal').modal('show');
            }
        }
    });
});

$(document).on('submit', '#updateCategories', function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    formData.append("update_categories", true);

    $('.error-message').text('');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            var res = jQuery.parseJSON(response);

            $('#errorMessage').addClass('d-none').text('');

            if (res.status == 422) {
                $.each(res.errors, function(field, message) {
                    $('<div class="error-message text-danger">' + message + '</div>').insertAfter('[name="' + field + '"]');
                });
            } else if (res.status == 200) {
                $('#errorMessage').addClass('d-none');
                alertify.set('notifier', 'position', 'top-right');
                alertify.success('<i class="fa fa-check-circle"></i> ' + res.message);
                $('#editCategoryModal').modal('hide');
                $('#updateCategories')[0].reset();

                setTimeout(function () {
                    location.reload();
                }, 2000);
            } else if (res.status == 500) {
                alert(res.message);
            }
        }
    });
});

$(document).on('click', '.deleteCategoryBtn', function (e) {
    e.preventDefault();

    var category_id = $(this).val();
            
    $('#confirmDeleteModal').data('category_id', category_id);   
    $('#confirmDeleteModal').modal('show');
});


$(document).on('click', '#confirmDeleteBtn', function () {
    var category_id = $('#confirmDeleteModal').data('category_id');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: {
            'delete_category': true,
            'category_id': category_id
        },
        success: function (response) {
            var res = jQuery.parseJSON(response);
            if (res.status == 500) {
                alert(res.message);
            } else {
                alertify.set('notifier','position', 'top-right');
                alertify.success('<i class="fa fa-check-circle"></i> ' + res.message);

                setTimeout(function(){
                    location.reload();
                }, 2000);
            }
        }
    });

    $('#confirmDeleteModal').modal('hide');
});

$(document).on('submit', '#saveProducts', function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    formData.append("save_products", true);

    $('.error-message').text('');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            var res = jQuery.parseJSON(response);

            $('#errorMessage').addClass('d-none').text('');
            $('#successMessage').addClass('d-none').text('');

            if (res.status == 422) {
                $.each(res.errors, function(field, message) {
                    $('<div class="error-message text-danger">' + message + '</div>').insertAfter('[name="' + field + '"]');
                });
            } else if (res.status == 200) {
                $('#successMessage').removeClass('d-none').text(res.message);
                $('#addProductModal').modal('hide');
                $('#saveProducts')[0].reset();
                alertify.set('notifier','position', 'top-right');
                alertify.success('<i class="fa fa-check-circle"></i> ' + res.message);

                setTimeout(function () {
                    location.reload();
                }, 2000);
            } else if (res.status == 500) {
                alert(res.message);
            }
        }
    });
});

$(document).on('click', '.editProductBtn', function () {
    var product_id = $(this).val();
    
    $.ajax({
        type: "GET",
        url: "query.php?product_id=" + product_id,
        success: function (response) {
            var res = jQuery.parseJSON(response);

            if(res.status == 404) {
                alert(res.message);
            } else if(res.status == 200) {
                $('#product_id').val(res.data.product_id);
                $('#edit_product_name').val(res.data.product_name);
                $('#edit_category_id').val(res.data.category_id);
                $('#edit_price').val(res.data.price);
                $('#edit_stock').val(res.data.stock);

                $('#editProductModal').modal('show');
            }
        }
    });
});

$(document).on('submit', '#updateProducts', function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    formData.append("update_products", true);

    $('.error-message').text('');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            var res = jQuery.parseJSON(response);

            $('#errorMessage').addClass('d-none').text('');

            if (res.status == 422) {
                $.each(res.errors, function(field, message) {
                    $('<div class="error-message text-danger">' + message + '</div>').insertAfter('[name="' + field + '"]');
                });
            } else if (res.status == 200) {
                $('#errorMessage').addClass('d-none');
                alertify.set('notifier', 'position', 'top-right');
                alertify.success('<i class="fa fa-check-circle"></i> ' + res.message);
                $('#editProductModal').modal('hide');
                $('#updateProducts')[0].reset();

                setTimeout(function () {
                    location.reload();
                }, 2000);
            } else if (res.status == 500) {
                alert(res.message);
            }
        }
    });
});

$(document).on('click', '.deleteProductBtn', function (e) {
    e.preventDefault();

    var product_id = $(this).val();
            
    $('#confirmDeleteModal').data('product_id', product_id);   
    $('#confirmDeleteModal').modal('show');
});


$(document).on('click', '#confirmDeleteBtn', function () {
    var product_id = $('#confirmDeleteModal').data('product_id');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: {
            'delete_product': true,
            'product_id': product_id
        },
        success: function (response) {
            var res = jQuery.parseJSON(response);
            if (res.status == 500) {
                alert(res.message);
            } else {
                alertify.set('notifier','position', 'top-right');
                alertify.success('<i class="fa fa-check-circle"></i> ' + res.message);

                setTimeout(function(){
                    location.reload();
                }, 2000);
            }
        }
    });

    $('#confirmDeleteModal').modal('hide');
});

$(document).on('submit', '#saveSales', function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    formData.append("save_sales", true);

    $('.error-message').text('');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            var res = jQuery.parseJSON(response);

            $('#errorMessage').addClass('d-none').text('');
            $('#successMessage').addClass('d-none').text('');

            if (res.status == 422) {
                $.each(res.errors, function(field, message) {
                    $('<div class="error-message text-danger">' + message + '</div>').insertAfter('[name="' + field + '"]');
                });
            } else if (res.status == 200) {
                $('#successMessage').removeClass('d-none').text(res.message);
                $('#addSaleModal').modal('hide');
                $('#saveSales')[0].reset();
                alertify.set('notifier','position', 'top-right');
                alertify.success('<i class="fa fa-check-circle"></i> ' + res.message);

                setTimeout(function () {
                    location.reload();
                }, 2000);
            } else if (res.status == 500) {
                alert(res.message);
            }
        }
    });
});

$(document).on('click', '.deleteSaleBtn', function (e) {
    e.preventDefault();

    var sale_id = $(this).val();
            
    $('#confirmDeleteModal').data('sale_id', sale_id);   
    $('#confirmDeleteModal').modal('show');
});


$(document).on('click', '#confirmDeleteBtn', function () {
    var sale_id = $('#confirmDeleteModal').data('sale_id');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: {
            'delete_sale': true,
            'sale_id': sale_id
        },
        success: function (response) {
            var res = jQuery.parseJSON(response);
            if (res.status == 500) {
                alert(res.message);
            } else {
                alertify.set('notifier','position', 'top-right');
                alertify.success('<i class="fa fa-check-circle"></i> ' + res.message);

                setTimeout(function(){
                    location.reload();
                }, 2000);
            }
        }
    });

    $('#confirmDeleteModal').modal('hide');
});

// Save Variant
$(document).on('submit', '#saveVariant', function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    formData.append("save_variant", true);

    $('.error-message').text('');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            var res = jQuery.parseJSON(response);

            if (res.status == 422) {
                $.each(res.errors, function(field, message) {
                    $('<div class="error-message text-danger">' + message + '</div>')
                        .insertAfter('[name="' + field + '"]');
                });
            } else if (res.status == 200) {
                $('#addVariantModal').modal('hide');
                $('#saveVariant')[0].reset();
                alertify.set('notifier','position', 'top-right');
                alertify.success('<i class="fa fa-check-circle"></i> ' + res.message);
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                alert(res.message);
            }
        }
    });
});

// Edit Variant (Fetch Data)
$(document).on('click', '.editVariantBtn', function () {
    var variant_id = $(this).val();

    $.ajax({
        type: "GET",
        url: "query.php?variant_id=" + variant_id,
        success: function (response) {
            var res = jQuery.parseJSON(response);

            if(res.status == 404) {
                alert(res.message);
            } else if(res.status == 200) {
                $('#edit_variant_id').val(res.data.variant_id);
                $('#edit_variant_name').val(res.data.variant_name);
                $('#edit_price').val(res.data.price);
                $('#edit_stock').val(res.data.stock);
                $('#editVariantModal').modal('show');
            }
        }
    });
});

// Update Variant
$(document).on('submit', '#updateVariant', function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    formData.append("update_variant", true);

    $('.error-message').text('');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            var res = jQuery.parseJSON(response);

            if (res.status == 422) {
                $.each(res.errors, function(field, message) {
                    $('<div class="error-message text-danger">' + message + '</div>')
                        .insertAfter('[name="' + field + '"]');
                });
            } else if (res.status == 200) {
                $('#editVariantModal').modal('hide');
                $('#updateVariant')[0].reset();
                alertify.set('notifier', 'top-right');
                alertify.success('<i class="fa fa-check-circle"></i> ' + res.message);
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                alert(res.message);
            }
        }
    });
});

// Delete Variant (Open Modal)
$(document).on('click', '.deleteVariantBtn', function (e) {
    e.preventDefault();
    var variant_id = $(this).val();
    $('#deleteVariantModal').data('variant_id', variant_id).modal('show');
});

// Confirm Delete
$(document).on('click', '#confirmDeleteVariantBtn', function () {
    var variant_id = $('#deleteVariantModal').data('variant_id');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: {
            'delete_variant': true,
            'variant_id': variant_id
        },
        success: function (response) {
            var res = jQuery.parseJSON(response);

            if (res.status == 200) {
                $('#deleteVariantModal').modal('hide');
                alertify.set('notifier','position', 'top-right');
                alertify.success('<i class="fa fa-check-circle"></i> ' + res.message);
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                alert(res.message);
            }
        }
    });
});

// Load variants when product is selected in Add Sale
$(document).on('change', '#product_id', function () {
    var product_id = $(this).val();

    if (product_id) {
        $.ajax({
            type: "GET",
            url: "query.php",
            data: { get_variants_by_product: product_id },
            success: function (response) {
                var res = jQuery.parseJSON(response);
                var variantSelect = $('#variant_id');

                variantSelect.empty().prop('disabled', false);

                if (res.status == 200 && res.data.length > 0) {
                    variantSelect.append('<option disabled selected>Select Variant</option>');
                    $.each(res.data, function(index, variant) {
                        variantSelect.append(
                            '<option value="' + variant.variant_id + '">' + variant.variant_name + ' (₱' + variant.price + ', Stock: ' + variant.stock + ')</option>'
                        );
                    });
                } else {
                    variantSelect.append('<option disabled>No variants available</option>');
                }
            }
        });
    }
});

// =============================
// 💼 SAVE SUPPLIER (AJAX)
// =============================
$(document).on('submit', '#saveSupplier', function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    formData.append("save_supplier", true);

    $('.error-message').text('');

    $.ajax({
        type: "POST",
        url: "query.php",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            var res = jQuery.parseJSON(response);

            if (res.status == 422) {
                $.each(res.errors, function (field, message) {
                    $('<div class="error-message text-danger">' + message + '</div>')
                        .insertAfter('[name="' + field + '"]');
                });
            } 
            else if (res.status == 200) {
                $('#addSupplierModal').modal('hide');
                $('#saveSupplier')[0].reset();

                alertify.set('notifier', 'position', 'top-right');
                alertify.success('<i class="fa fa-check-circle"></i> ' + res.message);

                // ✅ Dynamically add new supplier to dropdown without reload
                if (res.new_supplier) {
                    const select = $('#supplierSelect');
                    select.append(
                        $('<option>', {
                            value: res.new_supplier.id,
                            text: res.new_supplier.name,
                            selected: true
                        })
                    ).trigger('change');
                }
            } 
            else if (res.status == 500) {
                alert(res.message); 
            }
        }
    });
});


<?php
// includes/footer.php
?>
                    </main>
                </div>
                
                <!-- Footer -->
                <footer class="footer mt-auto py-3 bg-white border-top">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <span class="text-muted">© <?php echo date('Y'); ?> UNEXCA - Universidad Nacional Experimental de la Gran Caracas</span>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <span class="text-muted">Versión 1.0.0</span>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Inicializar DataTables
        $(document).ready(function() {
            $('.datatable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                responsive: true
            });
            
            // Inicializar Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
            
            // Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Confirmación de eliminación
            $('.confirm-delete').on('click', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                
                Swal.fire({
                    title: '¿Está seguro?',
                    text: "Esta acción no se puede deshacer",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
        });
        
        // Mostrar/ocultar sidebar en móviles
        document.getElementById('sidebar').addEventListener('show.bs.collapse', function () {
            document.querySelector('.main-content').style.opacity = '0.7';
        });
        
        document.getElementById('sidebar').addEventListener('hidden.bs.collapse', function () {
            document.querySelector('.main-content').style.opacity = '1';
        });
    </script>
    
    <!-- Scripts personalizados por módulo -->
    <?php if (isset($custom_scripts)): ?>
    <?php foreach ($custom_scripts as $script): ?>
    <script src="../assets/js/<?php echo $script; ?>"></script>
    <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
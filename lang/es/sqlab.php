<?php
// Cadenas para el componente 'mod_sqlab', idioma 'es'.

// Información General del Módulo
$string['sqlab'] = 'SQLab';
$string['pluginname'] = 'SQLab';
$string['modulename'] = 'SQLab';
$string['modulenameplural'] = 'SQLabs';
$string['pluginadministration'] = 'Administración de SQLab';
$string['privacy:metadata'] = 'El plugin SQLab no almacena datos personales.';
$string['privacy:metadata:preference:description'] = 'Tus ajustes personales para SQLab, como personalizaciones de la interfaz de usuario.';

// Estado y Navegación
$string['nosqlabs'] = 'No hay instancias de SQLab';
$string['state'] = 'Estado';
$string['inprogress'] = 'En curso';
$string['finished'] = 'Finalizado';
$string['overdue'] = 'Atrasado';
$string['previouspage'] = 'Página anterior';
$string['nextpage'] = 'Siguiente página';
$string['returntoattempt'] = 'Volver al intento';

// Interacción del Usuario
$string['attempt'] = 'Intento';
$string['continueattempt'] = 'Continuar el último intento';
$string['startnewattempt'] = 'Comenzar un nuevo intento';
$string['previousattempts'] = 'Intentos anteriores';
$string['finishattempt'] = 'Terminar intento...';
$string['submitandfinish'] = 'Enviar todo y terminar';

// Gestión de Preguntas y Respuestas
$string['question'] = 'Pregunta';
$string['saved'] = 'Respuesta guardada';
$string['notsaved'] = 'Aún sin responder';
$string['questionnavtittle'] = 'Navegación de preguntas';
$string['noresponseprovided'] = '-- No se ha proporcionado una respuesta.';
$string['userresponsereview'] = 'Su respuesta';
$string['solutionreview'] = 'Solución';

// Permisos y Roles
$string['sqlab:addinstance'] = 'Agregar un nuevo SQLab';
$string['sqlab:view'] = 'Ver SQLab';
$string['sqlab:manage'] = 'Gestionar SQLab';
$string['sqlab:attempt'] = 'Permitir al usuario intentar la actividad SQLab';

// Calificaciones e Intentos
$string['attemptsummary'] = 'Resumen del intento';
$string['gradesummary'] = '<strong>{$a->obtained}</strong> de {$a->total} (<strong>{$a->percentage}</strong>%)';
$string['gradereview'] = 'Calificación: {$a->usergrade}/{$a->totalgrade}';
$string['permittedattempts'] = 'Intentos permitidos: ';
$string['unlimitedattempts'] = 'Intentos ilimitados.';
$string['grade'] = 'Calificación';

// Errores y Mensajes del Sistema
$string['invalidcoursemodule'] = 'La actividad a la que intenta acceder no existe. Por favor, contacte a soporte si el problema persiste.';
$string['invalidcourseid'] = 'El curso al que intenta acceder no existe. Por favor, contacte a soporte si el problema persiste.';
$string['invalidattemptid'] = 'El intento al que intenta acceder no existe o ya no está disponible. Si cree que esto es un error, por favor contacte a soporte.';
$string['missingparam'] = 'Falta información necesaria. Por favor, contacte a soporte si el problema persiste.';
$string['missingparameters'] = 'Faltan algunos parámetros requeridos. Por favor, verifica tu entrada e intenta de nuevo.';
$string['invalidrequestmethod'] = 'El método de solicitud no es compatible con este punto de acceso. Por favor, utiliza POST.';
$string['errorprocessattempt'] = 'Se produjo un error al procesar su intento. Por favor, inténtelo de nuevo o contacte con soporte si el problema persiste.';
$string['noattemptid'] = 'No se proporcionó un ID de intento. Por favor, contacte a soporte si el problema persiste.';
$string['notyourattempt'] = 'No tiene permiso para acceder a este intento. Si cree que esto es un error, por favor contacte a soporte.';
$string['invalidsqlabid'] = 'La actividad SQLab a la que intentas acceder no existe o ha sido eliminada. Si cree que esto es un error, por favor contacte a soporte.';
$string['noquestionsfound'] = 'No se encontraron preguntas para esta actividad de SQLab. Por favor, contacte a soporte si cree que esto es un error.';
$string['noquestionid'] = 'No se proporcionó el ID de la pregunta. Por favor, contacte al soporte si el problema persiste.';
$string['questionnotfound'] = 'La pregunta solicitada no pudo ser encontrada. Puede que haya sido eliminada o esté temporalmente no disponible. Por favor, contacte al soporte si el problema persiste.';
$string['nomoreattempts'] = 'Has alcanzado el número máximo de intentos para esta actividad.';
$string['nosqlcode'] = 'No se proporcionó código SQL. Por favor, contacte al soporte si el problema persiste.';
$string['noevaluate'] = 'Falta el parámetro de evaluación. Por favor, contacte al soporte si el problema persiste.';
$string['error_details'] = '<br><br>&nbsp;&nbsp;&nbsp; • Detalles:';
$string['view_names_not_found'] = 'No se pudieron encontrar los nombres de las vistas en la consulta.';
$string['sql_file_not_exist'] = 'El archivo SQL no existe en la ruta especificada: {$a}';
$string['unable_to_load_sql'] = 'No se pudo cargar la definición de la función del archivo SQL.';
$string['function_creation_failed'] = 'Error al crear la función: {$a}';
$string['attemptnotfound'] = 'Intento no encontrado.';
$string['sqlabnotfound'] = 'Instancia de SQLab no encontrada.';
$string['schemanameerror'] = 'Error al formatear el nombre del esquema.';
$string['nocredentials'] = 'No se encontraron credenciales para el usuario.';
$string['dbconnectionerror'] = 'No se puede conectar a la base de datos para la instancia de SQLab.';
$string['nocmid'] = 'No se proporcionó un ID de módulo de curso. Por favor, contacta al soporte si el problema persiste.';

// Configuración y Configuración
$string['quizid'] = 'ID del Cuestionario';
$string['quizid_help'] = 'la obtención del ID del Cuestionario con las preguntas SQL';
$string['quizid_help_help'] = 'Para configurar SQLab correctamente, necesitas ingresar el ID de un Cuestionario con preguntas SQL que deseas que SQLab utilice. Cada cuestionario tiene un ID único asociado. Esta ID es fundamental para que SQLab pueda encontrar y cargar las preguntas SQL que deseas utilizar.';
$string['submissionperiod'] = 'Periodo de entrega';
$string['startdate'] = 'Disponible desde';
$string['duedate'] = 'Límite de entrega';

// Seguridad y Control de Acceso
$string['securitysettings'] = 'Seguridad';
$string['activitypassword'] = 'Contraseña de la actividad';
$string['sqlabpasswordrequired'] = 'Para acceder a este SQLab es necesario conocer la contraseña.';
$string['passwordmodaltitle'] = 'Contraseña';
$string['enterpassword'] = 'Por favor, ingrese la contraseña para continuar:';
$string['closemodalpassword'] = 'Cerrar';
$string['sendpassword'] = 'Continuar';
$string['passwordincorrect'] = 'Contraseña incorrecta.';
$string['passwordempty'] = 'Por favor, introduzca una contraseña.';
$string['unexpectederror'] = 'Se ha producido un error inesperado.';
$string['ajaxerror'] = 'Error en la solicitud AJAX.';

// Revisiones y Retroalimentación
$string['review'] = 'Revisión';
$string['reviewlinktext'] = 'Revisar intento';
$string['startedon'] = 'Iniciado el';
$string['completedon'] = 'Completado el';
$string['timetaken'] = 'Tiempo transcurrido';
$string['reviewgrade'] = 'Calificación';
$string['finishreview'] = 'Finalizar revisión';
$string['feedbackreview'] = 'Retroalimentación';

// Personalización de la Interfaz de la Actividad
$string['name'] = 'Nombre de la actividad';
$string['editorthemes'] = 'Temas del editor';
$string['fontsize'] = 'Tamaño de letra';
$string['runcode'] = 'Ejecutar código';
$string['evaluatecode'] = 'Evaluar código';
$string['beforefinish'] = 'Para probar tus respuestas sin que afecten tu evaluación, utiliza el botón "Ejecutar código". Esto te permite verificar y ajustar tus respuestas cuantas veces necesites. Cuando estés completamente seguro del código presentado y quieras que se considere como tu respuesta final, presiona "Evaluar código". Recuerda que el código que esté escrito en el momento de hacer clic en "Evaluar código" será considerado como tu respuesta definitiva en la evaluación.';
$string['scoresas'] = 'Puntúa como';
$string['sqlresults'] = 'Resultados esperados';
$string['relatedconcepts'] = 'Conceptos relacionados';
$string['hints'] = 'Pistas';

// Retroalimentación
$string['yes'] = 'Sí';
$string['no'] = 'No';
$string['not_present'] = 'No presente';
$string['row'] = 'Fila';
$string['is_correct'] = '¿Es correcto?';
$string['status'] = 'Estado';
$string['your_answer'] = 'Su respuesta';
$string['expected_answer'] = 'Respuesta esperada';
$string['all_rows_correct_feedback'] = 'Todas las filas son correctas.';
$string['no_response_feedback'] = 'No se ha proporcionado una respuesta.';

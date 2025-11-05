pipeline {
    agent any

    environment {
        SWARM_STACK_NAME = "app"
        DB_SERVICE = 'db'
        DB_USER = 'root'
        DB_PASSWORD = 'secret'
        DB_NAME = 'lena'
        FRONTEND_URL = 'http://192.168.0.1:8080'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }
        
        stage('Build Docker Images') {
            steps {
                script {
                    sh "docker build -f php.Dockerfile -t draktorio/crudback ."
                    
                    // Принудительно пересобираем MySQL образ без кэша
                    sh "docker build --no-cache -f mysql.Dockerfile -t draktorio/mysql ."
                    
                    // Проверяем что в образе правильный SQL файл
                    sh """
                        echo "=== Проверка содержимого SQL файла в образе ==="
                        docker run --rm draktorio/mysql cat /docker-entrypoint-initdb.d/init.sql | grep -i "description"
                    """
                }
            } 
        }
            
        stage('Remove Old Stack and Volumes') {
            steps {
                script {
                    // Принудительно удаляем стек и тома
                    sh """
                        # Удаляем стек
                        docker stack rm ${SWARM_STACK_NAME} || true
                        sleep 30
                        
                        # Удаляем все тома связанные с приложением
                        docker volume ls -q --filter name=${SWARM_STACK_NAME} | xargs -r docker volume rm -f || true
                        
                        # Очищаем все неиспользуемые тома
                        docker volume prune -f || true
                        
                        # Убеждаемся что все контейнеры остановлены
                        docker ps -aq --filter name=${SWARM_STACK_NAME} | xargs -r docker rm -f || true
                        
                        # Дополнительная очистка сетей
                        docker network prune -f || true
                    """
                }
            }
        }
            
        stage('Deploy to Docker Swarm') {
            steps {
                script {
                    sh '''
                        if ! docker info | grep -q "Swarm: active"; then
                            docker swarm init || true
                        fi
                    '''
                    sh "docker stack deploy --with-registry-auth -c docker-compose.yaml ${SWARM_STACK_NAME}"
                }
            }
        } 
        
        stage('Run Tests') {
            steps {
                script {
                    echo "Ожидание запуска сервисов..."
                    sleep 40
                    
                    // Ждем пока база данных полностью инициализируется
                    echo "Ожидание инициализации базы данных..."
                    sleep 30
                    
                    echo 'Проверка доступности фронта...'
                    sh """
                        if ! curl -fsS ${FRONTEND_URL}; then
                           echo 'Front недоступен'
                           exit 1
                        fi
                    """
                    
                    echo 'Получение ID контейнера базы данных...'
                    def dbContainerId = sh(
                        script: "docker ps --filter name=${SWARM_STACK_NAME}_${DB_SERVICE} --format '{{.ID}}'",
                        returnStdout: true
                    ).trim()
                    
                    if (!dbContainerId) {
                        error("Контейнер DB не найден")
                    }
                    
                    // Ждем пока база данных будет готова
                    echo "Ожидание готовности базы данных..."
                    sh """
                        timeout 120s bash -c '
                            until docker exec ${dbContainerId} mysql -u${DB_USER} -p${DB_PASSWORD} -e "SELECT 1;" > /dev/null 2>&1; do
                                echo "Ждем базу данных..."
                                sleep 10
                            done
                        '
                    """
                    
                    // Проверяем выполнился ли SQL скрипт
                    echo "Проверка выполнения SQL скрипта инициализации..."
                    sh """
                        docker logs ${dbContainerId} | grep -i "init.sql" || echo "SQL скрипт не найден в логах"
                    """
                    
                    echo 'Подключение к MySQL и проверка таблиц...'
                    sh """
                        docker exec ${dbContainerId} mysql -u${DB_USER} -p${DB_PASSWORD} -e 'USE ${DB_NAME}; SHOW TABLES;'
                    """

                    // ==============================
                    // Проверка типа поля description
                    // ==============================
                    echo 'Проверка типа поля description...'

                    // Дополнительная проверка - выводим структуру таблицы для отладки
                    echo 'Вывод структуры таблицы records:'
                    sh """
                        docker exec ${dbContainerId} mysql -u${DB_USER} -p${DB_PASSWORD} -D ${DB_NAME} -e "DESCRIBE records;" || true
                    """
                    
                    // Проверяем что именно в SQL файле
                    echo "Проверка SQL файла в контейнере:"
                    sh """
                        docker exec ${dbContainerId} cat /docker-entrypoint-initdb.d/init.sql | grep -A 5 -B 5 "description" || true
                    """

                    def fieldType = sh(
                        script: """
                            docker exec ${dbContainerId} mysql -u${DB_USER} -p${DB_PASSWORD} -D ${DB_NAME} -N -e \\
                            "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='records' AND COLUMN_NAME='description';" 2>/dev/null || echo "unknown"
                        """,
                        returnStdout: true
                    ).trim().toLowerCase()

                    echo "Тип поля description: ${fieldType}"

                    // Исправленная проверка
                    if (fieldType == 'varchar') {
                        error("ОШИБКА: Найдено недопустимое значение VARCHAR для поля description. Ожидается TEXT.")
                    } else if (fieldType == 'text') {
                        echo "УСПЕХ: Поле description имеет правильный тип TEXT. Проверка пройдена!"
                    } else {
                        error("НЕИЗВЕСТНЫЙ ТИП: Поле description имеет неожиданный тип: ${fieldType}")
                    }
                    
                    // Дополнительная проверка - выводим все данные для отладки
                    echo 'Вывод всех записей из таблицы:'
                    sh """
                        docker exec ${dbContainerId} mysql -u${DB_USER} -p${DB_PASSWORD} -D ${DB_NAME} -e "SELECT * FROM records;" || true
                    """
                }
            }
        }  
    }

    post {
        success {
            echo 'Deployment completed successfully!'
        }
        failure {
            echo 'Deployment failed!'
        }
        always {
            cleanWs()
        }
    }
}
